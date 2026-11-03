<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Policies;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'Admin', 'Responsable'])
            || $user->can('read-Communications');
    }

    public function view(User $user, EmailTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Users cannot create email templates. Templates are system-managed only.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Users cannot update email templates. Templates are system-managed only.
     */
    public function update(User $user, EmailTemplate $template): bool
    {
        return false;
    }

    /**
     * Users cannot delete email templates. Templates are system-managed only.
     */
    public function delete(User $user, EmailTemplate $template): bool
    {
        return false;
    }
}
