<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\ChecklistTemplate;

class ChecklistTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-OperationalHub')
            || $user->can('read-checklist_templates')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?ChecklistTemplate $template = null): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Checklist templates are configuration data; only managers/admins may
     * create or redesign them. Technicians consume instantiated checklists.
     */
    public function create(User $user): bool
    {
        return $user->can('create-OperationalHub')
            || $user->can('create-checklist_templates')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?ChecklistTemplate $template = null): bool
    {
        return $user->can('edit-OperationalHub')
            || $user->can('edit-checklist_templates')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?ChecklistTemplate $template = null): bool
    {
        return $user->can('delete-OperationalHub')
            || $user->can('delete-checklist_templates')
            || $user->hasRole('admin');
    }
}
