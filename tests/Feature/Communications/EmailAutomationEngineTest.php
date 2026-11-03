<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Communications\Email\Automation\Enums\AutomationActionType;
use App\Modules\Communications\Email\Automation\Enums\AutomationExecutionMode;
use App\Modules\Communications\Email\Automation\Enums\AutomationExecutionStatus;
use App\Modules\Communications\Email\Automation\Enums\AutomationTrigger;
use App\Modules\Communications\Email\Automation\Models\EmailApprovalRequest;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationLog;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationRule;
use App\Modules\Communications\Email\Automation\Models\EmailFollowUpTask;
use App\Modules\Communications\Email\Automation\Models\EmailInternalNotification;
use App\Modules\Communications\Email\Automation\Services\EmailAutomationEngineService;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Finance\Models\SalesInvoice;
use Database\Seeders\EmailSenderProfileSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailAutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $salesManager;

    private Client $client;

    private EmailSenderProfile $senderProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailSenderProfileSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        Permission::findOrCreate('access-email', 'web');
        Permission::findOrCreate('read-Communications', 'web');
        Permission::findOrCreate('manage-Communications', 'web');

        $adminRole = Role::findOrCreate('Admin', 'web');

        $this->adminUser = User::factory()->create([
            'email' => 'admin@waveplus.xyz',
        ]);
        $this->adminUser->assignRole($adminRole);

        $this->salesManager = User::factory()->create([
            'email' => 'manager.sales@waveplus.xyz',
        ]);
        $this->salesManager->givePermissionTo(['access-email', 'read-Communications', 'manage-Communications']);

        $this->senderProfile = EmailSenderProfile::where('email', 'sales@waveplus.xyz')->first();

        $this->client = Client::create([
            'client_id' => 'CL-AUTO-1',
            'legal_name' => 'Acme Automated Industries',
            'primary_email' => 'procurement@acme-auto.com',
            'currency' => 'EUR',
            'status' => 'active',
        ]);
    }

    public function test_automatic_mode_executes_and_queues_email_on_quotation_sent(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Auto Quotation Sent',
            'slug' => 'auto-quotation-sent',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::Automatic->value,
            'template_slug' => 'quotation-submission-fr',
            'sender_profile_id' => $this->senderProfile->id,
            'conditions' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'sent'],
            ],
            'actions' => [
                ['type' => AutomationActionType::SendEmail->value],
            ],
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-AUTO1',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 5000,
            'total_tva' => 1000,
            'total_ttc' => 6000,
        ]);

        $engine = app(EmailAutomationEngineService::class);
        $logs = $engine->trigger(AutomationTrigger::QuotationSent->value, $quotation);

        $this->assertCount(1, $logs);
        $log = $logs[0];

        $this->assertEquals(AutomationExecutionStatus::Executed->value, $log->status);
        $this->assertNotNull($log->email_message_id);

        $message = EmailMessage::find($log->email_message_id);
        $this->assertNotNull($message);
        $this->assertContains($message->status, [EmailMessage::STATUS_QUEUED, EmailMessage::STATUS_SENT]);
        $this->assertDatabaseHas('email_recipients', [
            'email_message_id' => $message->id,
            'email' => 'procurement@acme-auto.com',
        ]);
    }

    public function test_approval_required_mode_creates_approval_request_and_draft_waiting_for_employee(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'High Value Quote Approval Required',
            'slug' => 'high-value-quote-approval',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::ApprovalRequired->value,
            'template_slug' => 'quotation-submission-fr',
            'sender_profile_id' => $this->senderProfile->id,
            'conditions' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'sent'],
                ['field' => 'total_ttc', 'operator' => '>=', 'value' => 10000],
            ],
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-HIGHVAL',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 10000,
            'total_tva' => 2000,
            'total_ttc' => 12000,
        ]);

        $engine = app(EmailAutomationEngineService::class);
        $logs = $engine->trigger(AutomationTrigger::QuotationSent->value, $quotation);

        $this->assertCount(1, $logs);
        $log = $logs[0];

        $this->assertEquals(AutomationExecutionStatus::Pending->value, $log->status);
        $this->assertNotNull($log->approval_request_id);

        $approval = EmailApprovalRequest::find($log->approval_request_id);
        $this->assertNotNull($approval);
        $this->assertEquals(EmailApprovalRequest::STATUS_PENDING, $approval->status);

        // Message should be DRAFT, not queued yet!
        $message = EmailMessage::find($approval->email_message_id);
        $this->assertEquals(EmailMessage::STATUS_DRAFT, $message->status);

        // Approve via API
        $res = $this->actingAs($this->salesManager)
            ->postJson("/api/communications/email/automation/approvals/{$approval->id}/approve", [
                'notes' => 'Approved for client delivery.',
            ]);

        $res->assertStatus(200);

        $approval->refresh();
        $this->assertEquals(EmailApprovalRequest::STATUS_APPROVED, $approval->status);
        $this->assertEquals($this->salesManager->id, $approval->approved_by);
        $this->assertNotNull($approval->approved_at);

        // Once approved, message is moved to queued!
        $message->refresh();
        $this->assertContains($message->status, [EmailMessage::STATUS_QUEUED, EmailMessage::STATUS_SENT]);
    }

    public function test_authorized_employee_can_reject_approval_request_with_reason(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Approval Test',
            'slug' => 'approval-test-reject',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::ApprovalRequired->value,
            'template_slug' => 'quotation-submission-fr',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-REJECT',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 500,
            'total_tva' => 100,
            'total_ttc' => 600,
        ]);

        $engine = app(EmailAutomationEngineService::class);
        $logs = $engine->trigger(AutomationTrigger::QuotationSent->value, $quotation);
        $approval = EmailApprovalRequest::find($logs[0]->approval_request_id);

        // Reject via API
        $res = $this->actingAs($this->salesManager)
            ->postJson("/api/communications/email/automation/approvals/{$approval->id}/reject", [
                'reason' => 'Discount exceeds policy limits.',
            ]);

        $res->assertStatus(200);

        $approval->refresh();
        $this->assertEquals(EmailApprovalRequest::STATUS_REJECTED, $approval->status);
        $this->assertEquals('Discount exceeds policy limits.', $approval->rejection_reason);
        $this->assertEquals($this->salesManager->id, $approval->approved_by);

        // Message remains draft and is not queued
        $message = EmailMessage::find($approval->email_message_id);
        $this->assertEquals(EmailMessage::STATUS_DRAFT, $message->status);
    }

    public function test_manual_mode_creates_draft_and_follow_up_task(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Pending Quote Task Reminder',
            'slug' => 'pending-quote-task-reminder',
            'trigger' => AutomationTrigger::QuotationPending->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::Manual->value,
            'template_slug' => 'general-communication-fr',
            'actions' => [
                ['type' => AutomationActionType::PrepareDraft->value],
                [
                    'type' => AutomationActionType::CreateFollowUpTask->value,
                    'params' => [
                        'title' => 'Call client regarding pending quotation',
                        'due_days' => 2,
                    ],
                ],
            ],
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-PENDING',
            'client_id' => $this->client->id,
            'status' => 'under_review',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
        ]);

        $engine = app(EmailAutomationEngineService::class);
        $logs = $engine->trigger(AutomationTrigger::QuotationPending->value, $quotation);

        $this->assertCount(1, $logs);
        $log = $logs[0];

        $this->assertNotNull($log->email_message_id);
        $this->assertNotNull($log->follow_up_task_id);

        $task = EmailFollowUpTask::find($log->follow_up_task_id);
        $this->assertNotNull($task);
        $this->assertEquals('Call client regarding pending quotation', $task->title);
        $this->assertEquals(EmailFollowUpTask::STATUS_OPEN, $task->status);
    }

    public function test_duplicate_execution_is_prevented_by_idempotency_key(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Once Only Rule',
            'slug' => 'once-only-rule',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::Automatic->value,
            'template_slug' => 'quotation-submission-fr',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-IDEMP',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
        ]);

        $engine = app(EmailAutomationEngineService::class);

        // First trigger
        $firstLogs = $engine->trigger(AutomationTrigger::QuotationSent->value, $quotation);
        $this->assertCount(1, $firstLogs);

        // Immediate duplicate trigger
        $secondLogs = $engine->trigger(AutomationTrigger::QuotationSent->value, $quotation);
        $this->assertCount(1, $secondLogs);

        // Exactly 1 log entry exists in database
        $this->assertDatabaseCount('email_automation_logs', 1);
        $this->assertDatabaseCount('email_messages', 1);
    }

    public function test_automation_skips_when_record_is_cancelled(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Skip Cancelled Rule',
            'slug' => 'skip-cancelled-rule',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::Automatic->value,
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-CANCELLED',
            'client_id' => $this->client->id,
            'status' => 'rejected',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
        ]);

        $engine = app(EmailAutomationEngineService::class);
        $logs = $engine->trigger(AutomationTrigger::QuotationSent->value, $quotation);

        $this->assertEmpty($logs);
        $this->assertDatabaseCount('email_automation_logs', 0);
    }

    public function test_disabled_rule_is_never_triggered(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Inactive Rule',
            'slug' => 'inactive-rule',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => false, // disabled
            'execution_mode' => AutomationExecutionMode::Automatic->value,
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-INACTIVE',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
        ]);

        $engine = app(EmailAutomationEngineService::class);
        $logs = $engine->trigger(AutomationTrigger::QuotationSent->value, $quotation);

        $this->assertEmpty($logs);
    }

    public function test_rules_api_allows_updating_active_state_and_execution_mode(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Configurable Rule',
            'slug' => 'configurable-rule',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::Automatic->value,
        ]);

        $res = $this->actingAs($this->adminUser)
            ->putJson("/api/communications/email/automation/rules/{$rule->id}", [
                'is_active' => false,
                'execution_mode' => 'approval_required',
            ]);

        $res->assertStatus(200);

        $rule->refresh();
        $this->assertFalse($rule->is_active);
        $this->assertEquals('approval_required', $rule->execution_mode);
    }

    public function test_condition_evaluator_safely_filters_by_status_department_date_and_partner(): void
    {
        $evaluator = app(\App\Modules\Communications\Email\Automation\Services\ConditionEvaluatorService::class);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-COND',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 5000,
            'total_tva' => 1000,
            'total_ttc' => 6000,
        ]);

        // Matching conditions
        $matching = [
            ['field' => 'status', 'operator' => '=', 'value' => 'sent'],
            ['field' => 'total_ttc', 'operator' => '>=', 'value' => 5000],
            ['field' => 'currency', 'operator' => 'in', 'value' => ['EUR', 'USD']],
            ['field' => 'client_id', 'operator' => '=', 'value' => $this->client->id],
        ];
        $this->assertTrue($evaluator->evaluate($matching, $quotation));

        // Non-matching condition
        $nonMatching = [
            ['field' => 'status', 'operator' => '=', 'value' => 'draft'],
        ];
        $this->assertFalse($evaluator->evaluate($nonMatching, $quotation));
    }

    public function test_condition_evaluator_never_executes_arbitrary_php_or_sql(): void
    {
        $evaluator = app(\App\Modules\Communications\Email\Automation\Services\ConditionEvaluatorService::class);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-SAFE',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
        ]);

        // Attempt illegal operator like 'system(' or 'eval'
        $maliciousConditions = [
            ['field' => 'status', 'operator' => 'system("whoami")', 'value' => 'sent'],
            ['field' => 'status', 'operator' => 'OR 1=1--', 'value' => 'sent'],
        ];

        $this->assertFalse($evaluator->evaluate($maliciousConditions, $quotation));
    }

    public function test_failed_execution_can_be_retried(): void
    {
        $rule = EmailAutomationRule::create([
            'name' => 'Retryable Rule',
            'slug' => 'retryable-rule',
            'trigger' => AutomationTrigger::QuotationSent->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::Automatic->value,
            'template_slug' => 'quotation-submission-fr',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-RETRY',
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
        ]);

        // Create a failed log
        $log = EmailAutomationLog::create([
            'rule_id' => $rule->id,
            'trigger' => $rule->trigger,
            'record_type' => 'Quotation',
            'record_id' => (string) $quotation->id,
            'execution_mode' => 'automatic',
            'status' => 'failed',
            'idempotency_key' => 'retry-test-key-1',
            'attempts' => 1,
            'error_message' => 'Simulated transport outage.',
        ]);

        $res = $this->actingAs($this->adminUser)
            ->postJson("/api/communications/email/automation/logs/{$log->id}/retry");

        $res->assertStatus(200);
        $res->assertJsonFragment(['success' => true]);

        $log->refresh();
        $this->assertEquals('executed', $log->status);
        $this->assertEquals(2, $log->attempts);
        $this->assertNotNull($log->email_message_id);
    }

    public function test_scheduled_scanner_finds_overdue_invoices_and_due_maintenance(): void
    {
        // 1. Seed an overdue invoice
        SalesInvoice::create([
            'invoice_number' => 'INV-2026-OVERDUE1',
            'client_id' => $this->client->id,
            'type' => 'standard',
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'currency' => 'EUR',
            'invoice_date' => now()->subDays(45),
            'due_date' => now()->subDays(15),
            'subtotal_ht' => 3000,
            'tax_amount' => 600,
            'total_ttc' => 3600,
            'amount_paid' => 0,
            'amount_outstanding' => 3600,
        ]);

        // 2. Create rule for invoice.overdue
        EmailAutomationRule::create([
            'name' => 'Auto Overdue Invoice Notice',
            'slug' => 'auto-overdue-inv',
            'trigger' => AutomationTrigger::InvoiceOverdue->value,
            'is_active' => true,
            'execution_mode' => AutomationExecutionMode::Automatic->value,
            'template_slug' => 'payment-reminder-fr',
        ]);

        $engine = app(EmailAutomationEngineService::class);
        $summary = $engine->scanScheduledTriggers();

        $this->assertGreaterThanOrEqual(1, $summary['invoice_overdue']);
        $this->assertDatabaseHas('email_automation_logs', [
            'trigger' => AutomationTrigger::InvoiceOverdue->value,
            'record_type' => 'SalesInvoice',
        ]);
    }

    public function test_internal_notifications_can_be_listed_and_marked_as_read(): void
    {
        $notification = EmailInternalNotification::create([
            'user_id' => $this->salesManager->id,
            'title' => 'Urgent Equipment Alert',
            'message' => 'Periodic inspection due.',
            'type' => 'warning',
            'is_read' => false,
        ]);

        // List
        $resList = $this->actingAs($this->salesManager)
            ->getJson('/api/communications/email/automation/notifications');

        $resList->assertStatus(200);
        $resList->assertJsonFragment(['title' => 'Urgent Equipment Alert']);

        // Mark as read
        $resRead = $this->actingAs($this->salesManager)
            ->postJson("/api/communications/email/automation/notifications/{$notification->id}/read");

        $resRead->assertStatus(200);
        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }
}
