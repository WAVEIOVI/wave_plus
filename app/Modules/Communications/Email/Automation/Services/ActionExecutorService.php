<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Communications\Email\Automation\Enums\AutomationActionType;
use App\Modules\Communications\Email\Automation\Enums\AutomationExecutionMode;
use App\Modules\Communications\Email\Automation\Models\EmailApprovalRequest;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationLog;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationRule;
use App\Modules\Communications\Email\Automation\Models\EmailFollowUpTask;
use App\Modules\Communications\Email\Automation\Models\EmailInternalNotification;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Models\EmailTemplate;
use App\Modules\Communications\Email\Services\EmailRecordLinkService;
use App\Modules\Communications\Email\Services\EmailSendingService;
use App\Modules\Communications\Email\Services\TemplateVariableResolver;
use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ActionExecutorService
{
    public function __construct(
        protected EmailSendingService $sendingService,
        protected TemplateVariableResolver $variableResolver,
        protected EmailRecordLinkService $linkService
    ) {}

    /**
     * Execute configured actions for a rule and record.
     *
     * @return array{email_message_id: ?string, approval_request_id: ?int, follow_up_task_id: ?int, notification_id: ?int}
     */
    public function execute(EmailAutomationRule $rule, Model $record, EmailAutomationLog $log): array
    {
        $result = [
            'email_message_id' => null,
            'approval_request_id' => null,
            'follow_up_task_id' => null,
            'notification_id' => null,
        ];

        // Determine actions list (defaulting based on execution mode if actions array is empty)
        $actions = $rule->actions ?? [];
        if (empty($actions)) {
            $actions = match ($rule->execution_mode) {
                AutomationExecutionMode::ApprovalRequired->value => [['type' => AutomationActionType::CreateApprovalRequest->value]],
                AutomationExecutionMode::Manual->value => [['type' => AutomationActionType::PrepareDraft->value]],
                default => [['type' => AutomationActionType::SendEmail->value]],
            };
        }

        foreach ($actions as $actionConfig) {
            $actionType = $actionConfig['type'] ?? '';

            switch ($actionType) {
                case AutomationActionType::SendEmail->value:
                    if ($rule->execution_mode === AutomationExecutionMode::ApprovalRequired->value) {
                        // In approval mode, send_email becomes create_approval_request
                        $approval = $this->createApprovalRequest($rule, $record, $log, $actionConfig);
                        $result['approval_request_id'] = $approval->id;
                        $result['email_message_id'] = $approval->email_message_id;
                    } elseif ($rule->execution_mode === AutomationExecutionMode::Manual->value) {
                        // In manual mode, send_email becomes draft
                        $draft = $this->prepareDraft($rule, $record, $log, $actionConfig);
                        $result['email_message_id'] = $draft->id;
                    } else {
                        // Automatic send
                        $msg = $this->sendPredefinedEmail($rule, $record, $log, $actionConfig);
                        $result['email_message_id'] = $msg->id;
                    }
                    break;

                case AutomationActionType::PrepareDraft->value:
                    $draft = $this->prepareDraft($rule, $record, $log, $actionConfig);
                    $result['email_message_id'] = $draft->id;
                    break;

                case AutomationActionType::CreateApprovalRequest->value:
                    $approval = $this->createApprovalRequest($rule, $record, $log, $actionConfig);
                    $result['approval_request_id'] = $approval->id;
                    $result['email_message_id'] = $approval->email_message_id;
                    break;

                case AutomationActionType::CreateFollowUpTask->value:
                    $task = $this->createFollowUpTask($rule, $record, $log, $actionConfig);
                    $result['follow_up_task_id'] = $task->id;
                    break;

                case AutomationActionType::SendInternalNotification->value:
                    $notification = $this->sendInternalNotification($rule, $record, $log, $actionConfig);
                    $result['notification_id'] = $notification->id;
                    break;
            }
        }

        return $result;
    }

    /**
     * Send a predefined email automatically.
     */
    protected function sendPredefinedEmail(EmailAutomationRule $rule, Model $record, EmailAutomationLog $log, array $config): EmailMessage
    {
        $payload = $this->buildEmailPayload($rule, $record, $config);

        // Queue immediately via central sending service
        $message = $this->sendingService->createAndQueue($payload);

        // Associate with the record
        $this->linkRecordToMessage($record, $message, "Automated email via rule [{$rule->name}]");

        return $message;
    }

    /**
     * Prepare a draft email without queuing.
     */
    protected function prepareDraft(EmailAutomationRule $rule, Model $record, EmailAutomationLog $log, array $config): EmailMessage
    {
        $payload = $this->buildEmailPayload($rule, $record, $config);

        $message = $this->sendingService->createDraft($payload);

        $this->linkRecordToMessage($record, $message, "Draft prepared by automation [{$rule->name}]");

        return $message;
    }

    /**
     * Create an approval request for an authorized employee.
     */
    protected function createApprovalRequest(EmailAutomationRule $rule, Model $record, EmailAutomationLog $log, array $config): EmailApprovalRequest
    {
        // 1. Prepare draft email
        $payload = $this->buildEmailPayload($rule, $record, $config);
        $message = $this->sendingService->createDraft($payload);

        $this->linkRecordToMessage($record, $message, "Pending approval for automation [{$rule->name}]");

        // 2. Create approval request
        return EmailApprovalRequest::create([
            'automation_log_id' => $log->id,
            'email_message_id' => $message->id,
            'record_type' => class_basename($record),
            'record_id' => (string) $record->getKey(),
            'status' => EmailApprovalRequest::STATUS_PENDING,
            'requested_by' => $rule->created_by,
            'notes' => "Rule [{$rule->name}] requires approval before sending.",
        ]);
    }

    /**
     * Create a follow-up task.
     */
    protected function createFollowUpTask(EmailAutomationRule $rule, Model $record, EmailAutomationLog $log, array $config): EmailFollowUpTask
    {
        $title = $config['params']['title'] ?? 'Follow up on '.class_basename($record).' #'.($record->reference ?? $record->id);
        $dueDays = (int) ($config['params']['due_days'] ?? 2);
        $assignedTo = $this->resolveResponsibleUserId($record) ?? $rule->created_by;

        return EmailFollowUpTask::create([
            'automation_log_id' => $log->id,
            'record_type' => class_basename($record),
            'record_id' => (string) $record->getKey(),
            'assigned_to' => $assignedTo,
            'title' => $title,
            'description' => $config['params']['description'] ?? "Automated task generated from rule: {$rule->name}",
            'due_date' => now()->addDays($dueDays)->toDateString(),
            'status' => EmailFollowUpTask::STATUS_OPEN,
        ]);
    }

    /**
     * Send an internal notification.
     */
    protected function sendInternalNotification(EmailAutomationRule $rule, Model $record, EmailAutomationLog $log, array $config): EmailInternalNotification
    {
        $userId = $this->resolveResponsibleUserId($record) ?? $rule->created_by;
        if (! $userId) {
            $admin = User::first();
            $userId = $admin?->id;
        }

        $title = $config['params']['title'] ?? "Automation Alert: {$rule->name}";
        $msg = $config['params']['message'] ?? "Trigger [{$rule->trigger}] fired for ".class_basename($record).' #'.($record->reference ?? $record->id);

        return EmailInternalNotification::create([
            'user_id' => (string) $userId,
            'title' => $title,
            'message' => $msg,
            'type' => $config['params']['type'] ?? 'info',
            'record_type' => class_basename($record),
            'record_id' => (string) $record->getKey(),
        ]);
    }

    /**
     * Build standard email payload from rule, record, and template.
     *
     * @return array{to: array<array{email: string, name: ?string}>, subject: string, body_html: string, body_text: ?string, sender_profile_id: ?int}
     */
    protected function buildEmailPayload(EmailAutomationRule $rule, Model $record, array $config): array
    {
        // 1. Resolve recipients
        $recipients = $this->resolveRecipients($rule, $record, $config);

        // 2. Resolve Sender Profile
        $senderProfileId = $rule->sender_profile_id;
        if (! $senderProfileId) {
            $defaultProfile = EmailSenderProfile::default()->first() ?? EmailSenderProfile::active()->first();
            $senderProfileId = $defaultProfile?->id;
        }

        // 3. Resolve Template & Content
        $template = null;
        if ($rule->template_slug) {
            $template = EmailTemplate::where('slug', $rule->template_slug)->first();
        }

        $subject = $config['params']['subject'] ?? $template?->subject ?? ('Notification regarding '.class_basename($record));
        $bodyHtml = $config['params']['body_html'] ?? $template?->body_html ?? ('<p>Dear customer,<br>This is an automated communication regarding your record.</p>');
        $bodyText = $template?->body_text ?? strip_tags($bodyHtml);

        // Interpolate variables safely
        $context = $this->buildRecordVariableContext($record);
        $subject = $this->variableResolver->resolve($subject, $context);
        $bodyHtml = $this->variableResolver->resolve($bodyHtml, $context);
        $bodyText = $this->variableResolver->resolve($bodyText, $context);

        return [
            'to' => $recipients,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'sender_profile_id' => $senderProfileId,
        ];
    }

    /**
     * Resolve recipient email addresses based on record relationships.
     *
     * @return array<int, array{email: string, name: ?string}>
     */
    protected function resolveRecipients(EmailAutomationRule $rule, Model $record, array $config): array
    {
        // Fixed email
        if (! empty($rule->fixed_recipient_email)) {
            return [['email' => $rule->fixed_recipient_email, 'name' => 'Recipient']];
        }

        // Record Partner email (Client / Supplier)
        if ($record->client_id) {
            $client = Client::find($record->client_id);
            if ($client && $client->primary_email) {
                return [['email' => $client->primary_email, 'name' => $client->legal_name]];
            }
        }

        if ($record->supplier_id) {
            $supplier = Supplier::find($record->supplier_id);
            if ($supplier && $supplier->primary_email) {
                return [['email' => $supplier->primary_email, 'name' => $supplier->legal_name]];
            }
        }

        if ($record instanceof Client && $record->primary_email) {
            return [['email' => $record->primary_email, 'name' => $record->legal_name]];
        }

        if ($record instanceof Supplier && $record->primary_email) {
            return [['email' => $record->primary_email, 'name' => $record->legal_name]];
        }

        // Responsible employee
        $userId = $this->resolveResponsibleUserId($record);
        if ($userId) {
            $user = User::find($userId);
            if ($user && $user->email) {
                return [['email' => $user->email, 'name' => $user->name]];
            }
        }

        // Fallback default
        return [['email' => 'client@waveplus.xyz', 'name' => 'Valued Client']];
    }

    /**
     * Build variable interpolation context from record.
     */
    protected function buildRecordVariableContext(Model $record): array
    {
        $context = [
            'document' => [
                'reference' => $record->reference ?? $record->invoice_number ?? $record->mission_number ?? (string) $record->id,
                'date' => ($record->created_at ?? now())->toDateString(),
                'total' => isset($record->total_ttc) ? number_format((float) $record->total_ttc, 2) : '0.00',
            ],
            'company' => [
                'name' => 'WAVE PLUS',
                'email' => 'contact@waveplus.xyz',
            ],
        ];

        if ($record->client_id) {
            $client = Client::find($record->client_id);
            if ($client) {
                $context['recipient'] = ['name' => $client->legal_name];
            }
        }

        return $context;
    }

    protected function resolveResponsibleUserId(Model $record): ?string
    {
        return $record->assigned_to
            ?? $record->lead_technician_id
            ?? $record->approved_by
            ?? $record->created_by
            ?? null;
    }

    protected function linkRecordToMessage(Model $record, EmailMessage $message, string $reason): void
    {
        try {
            $user = User::first();
            if ($user) {
                $message->associations()->create([
                    'conversation_id' => $message->conversation_id,
                    'associable_type' => get_class($record),
                    'associable_id' => (string) $record->getKey(),
                    'link_type' => 'manual',
                    'is_confirmed' => true,
                    'confidence_score' => 1.00,
                    'match_reason' => $reason,
                    'linked_by' => $user->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed linking record to automated message: '.$e->getMessage());
        }
    }
}
