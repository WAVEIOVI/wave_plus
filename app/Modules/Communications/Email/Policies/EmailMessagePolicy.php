<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Policies;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Services\EmailAccessControlService;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailMessagePolicy
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

    public function viewAny(User $user): bool
    {
        return $user->can('read-Communications');
    }

    public function view(User $user, EmailMessage $message): bool
    {
        return $this->access()->canAccessMessage($user, $message);
    }

    public function create(User $user): bool
    {
        return $user->can('create-email-messages') || $user->can('create-Communications');
    }

    public function update(User $user, EmailMessage $message): bool
    {
        if (! $this->access()->canAccessMessage($user, $message)) {
            return false;
        }

        if ($user->can('edit-email-messages') || $user->can('edit-Communications')) {
            return true;
        }

        // Creator can edit their own drafts
        return $message->status === EmailMessage::STATUS_DRAFT
            && (string) $message->created_by === (string) $user->id;
    }

    public function delete(User $user, EmailMessage $message): bool
    {
        if (! $this->access()->canAccessMessage($user, $message)) {
            return false;
        }

        return ($user->can('delete-email-messages') || $user->can('delete-Communications'))
            && $message->status === EmailMessage::STATUS_DRAFT;
    }

    public function send(User $user, EmailMessage $message): bool
    {
        if (! $this->access()->canAccessMessage($user, $message)) {
            return false;
        }

        return $user->can('create-email-messages') || $user->can('create-Communications');
    }

    public function retry(User $user, EmailMessage $message): bool
    {
        if (! $this->access()->canAccessMessage($user, $message)) {
            return false;
        }

        return $user->can('create-email-messages')
            || $user->can('create-Communications')
            || $this->access()->isAdmin($user);
    }
}
