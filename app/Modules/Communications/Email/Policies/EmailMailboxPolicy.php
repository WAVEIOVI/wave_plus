<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Policies;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailMailbox;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailMailboxPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('read-Communications');
    }

    public function view(User $user, EmailMailbox $mailbox): bool
    {
        return $mailbox->isAuthorizedForUser($user);
    }

    public function sync(User $user, EmailMailbox $mailbox): bool
    {
        return $mailbox->isAuthorizedForUser($user);
    }

    public function manage(User $user): bool
    {
        return $user->can('manage-email-mailboxes');
    }
}
