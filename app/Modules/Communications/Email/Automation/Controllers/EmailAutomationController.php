<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Automation\Models\EmailApprovalRequest;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationLog;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationRule;
use App\Modules\Communications\Email\Automation\Models\EmailFollowUpTask;
use App\Modules\Communications\Email\Automation\Models\EmailInternalNotification;
use App\Modules\Communications\Email\Automation\Services\EmailAutomationEngineService;
use App\Modules\Communications\Email\Services\EmailAccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmailAutomationController extends Controller
{
    public function __construct(
        protected EmailAutomationEngineService $engine,
        protected EmailAccessControlService $accessControl,
    ) {}

    /**
     * List automation rules.
     * GET /api/communications/email/automation/rules
     */
    public function rules(Request $request): JsonResponse
    {
        Gate::authorize('viewRules', EmailAutomationRule::class);

        $query = EmailAutomationRule::with(['senderProfile', 'creator']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($trigger = $request->input('trigger')) {
            $query->where('trigger', $trigger);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Enable/disable or update execution mode of a rule.
     * PUT /api/communications/email/automation/rules/{rule}
     */
    public function updateRule(EmailAutomationRule $rule, Request $request): JsonResponse
    {
        Gate::authorize('updateRule', $rule);

        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
            'execution_mode' => 'sometimes|string|in:automatic,approval_required,manual',
            'sender_profile_id' => 'nullable|integer|exists:email_sender_profiles,id',
            'fixed_recipient_email' => 'nullable|email',
        ]);

        $rule->update($validated);

        return response()->json([
            'message' => 'Automation rule updated successfully.',
            'data' => $rule->fresh(),
        ]);
    }

    /**
     * List automation execution logs.
     * GET /api/communications/email/automation/logs
     */
    public function logs(Request $request): JsonResponse
    {
        Gate::authorize('viewLogs', EmailAutomationRule::class);

        $query = EmailAutomationLog::with(['rule', 'message', 'approvalRequest', 'followUpTask'])
            ->latest('id');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($trigger = $request->input('trigger')) {
            $query->where('trigger', $trigger);
        }

        if ($recordType = $request->input('record_type')) {
            $query->where('record_type', $recordType);
        }

        $paginated = $query->paginate($request->integer('per_page', 25));

        return response()->json($paginated);
    }

    /**
     * Retry a failed automation execution log.
     * POST /api/communications/email/automation/logs/{log}/retry
     */
    public function retryLog(int $log, Request $request): JsonResponse
    {
        Gate::authorize('retryLog', EmailAutomationRule::class);

        $success = $this->engine->retry($log);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Automation execution retried successfully.' : 'Retry failed or not applicable.',
            'data' => EmailAutomationLog::find($log),
        ]);
    }

    /**
     * List pending/approved approval requests.
     * GET /api/communications/email/automation/approvals
     */
    public function approvalRequests(Request $request): JsonResponse
    {
        Gate::authorize('viewApprovals', EmailAutomationRule::class);

        $query = EmailApprovalRequest::with(['message.recipients', 'automationLog.rule', 'requestedByUser', 'approvedByUser'])
            ->latest('id');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Apply message access scope so users only see approval requests for accessible messages
        if (! $this->accessControl->isAdmin($request->user())) {
            $query->whereHas('message', function ($mq) use ($request) {
                $this->accessControl->applyMessageScope($mq, $request->user());
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    /**
     * Approve an email approval request.
     * POST /api/communications/email/automation/approvals/{approval}/approve
     */
    public function approve(int $approval, Request $request): JsonResponse
    {
        $approvalModel = EmailApprovalRequest::with('message')->findOrFail($approval);

        Gate::authorize('approve', $approvalModel);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $approvalModel->approve($request->user(), $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Email approved and queued for sending.',
            'data' => $approvalModel->fresh(['approvedByUser', 'message']),
        ]);
    }

    /**
     * Reject an email approval request.
     * POST /api/communications/email/automation/approvals/{approval}/reject
     */
    public function reject(int $approval, Request $request): JsonResponse
    {
        $approvalModel = EmailApprovalRequest::with('message')->findOrFail($approval);

        Gate::authorize('reject', $approvalModel);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $approvalModel->reject($request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Approval request rejected.',
            'data' => $approvalModel->fresh(['approvedByUser']),
        ]);
    }

    /**
     * List follow-up tasks.
     * GET /api/communications/email/automation/tasks
     */
    public function tasks(Request $request): JsonResponse
    {
        Gate::authorize('viewTasks', EmailAutomationRule::class);

        $query = EmailFollowUpTask::with(['assignedUser', 'automationLog.rule'])
            ->latest('id');

        if (! $this->accessControl->isAdmin($request->user())) {
            $query->where('assigned_user_id', $request->user()->id);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    /**
     * Mark a follow-up task as complete.
     * POST /api/communications/email/automation/tasks/{task}/complete
     */
    public function completeTask(int $task, Request $request): JsonResponse
    {
        $taskModel = EmailFollowUpTask::findOrFail($task);

        Gate::authorize('completeTask', $taskModel);

        $taskModel->complete();

        return response()->json([
            'message' => 'Task marked as completed.',
            'data' => $taskModel->fresh(),
        ]);
    }

    /**
     * List internal notifications.
     * GET /api/communications/email/automation/notifications
     */
    public function notifications(Request $request): JsonResponse
    {
        $notifications = EmailInternalNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json($notifications);
    }

    /**
     * Mark notification as read.
     * POST /api/communications/email/automation/notifications/{notification}/read
     */
    public function markNotificationRead(int $notification, Request $request): JsonResponse
    {
        $notificationModel = EmailInternalNotification::where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $notificationModel->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notificationModel,
        ]);
    }
}
