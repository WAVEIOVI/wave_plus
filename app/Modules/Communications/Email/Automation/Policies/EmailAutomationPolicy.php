<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Policies;

use App\Models\User;
use App\Modules\Communications\Email\Automation\Models\EmailApprovalRequest;
use App\Modules\Communications\Email\Automation\Models\EmailAutomationRule;
use App\Modules\Communications\Email\Automation\Models\EmailFollowUpTask;
use App\Modules\Communications\Email\Services\EmailAccessControlService;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailAutomationPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly ?EmailAccessControlService $accessControl = null,
    ) {}

    protected function access(): EmailAccessControlService
    {
        return $this->accessControl ?? app(EmailAccessControlService::class);
    }

    public function before(User $user, string $ability): ?bool
    {
        if ($this->access()->isAdmin($user)) {
            return true;
        }

        return null;
    }

    public function viewRules(User $user): bool
    {
        return $user->can('read-Communications');
    }

    public function updateRule(User $user, EmailAutomationRule $rule): bool
    {
        return $user->can('edit-Communications') || $user->can('manage-settings');
    }

    public function viewLogs(User $user): bool
    {
        return $user->can('read-Communications');
    }

    public function retryLog(User $user): bool
    {
        return $user->can('edit-Communications') || $user->can('create-email-messages');
    }

    public function viewApprovals(User $user): bool
    {
        return $user->can('read-Communications');
    }

    public function approve(User $user, EmailApprovalRequest $approval): bool
    {
        if (! $user->can('read-Communications')) {
            return false;
        }

        if ($approval->message) {
            return $this->access()->canAccessMessage($user, $approval->message);
        }

        return true;
    }

    public function reject(User $user, EmailApprovalRequest $approval): bool
    {
        return $this->approve($user, $approval);
    }

    public function viewTasks(User $user): bool
    {
        return $user->can('read-Communications');
    }

    public function completeTask(User $user, EmailFollowUpTask $task): bool
    {
        if ($this->access()->isAdmin($user)) {
            return true;
        }

        return (string) $task->assigned_user_id === (string) $user->id;
    }
}
