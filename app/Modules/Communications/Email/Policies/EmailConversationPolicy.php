<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Policies;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Services\EmailAccessControlService;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailConversationPolicy
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

    public function view(User $user, EmailConversation $conversation): bool
    {
        return $this->access()->canAccessConversation($user, $conversation);
    }

    public function assign(User $user, EmailConversation $conversation): bool
    {
        return $this->access()->canAccessConversation($user, $conversation);
    }

    public function changeStatus(User $user, EmailConversation $conversation): bool
    {
        return $this->access()->canAccessConversation($user, $conversation);
    }

    public function addNote(User $user, EmailConversation $conversation): bool
    {
        return $this->access()->canAccessConversation($user, $conversation);
    }
}
