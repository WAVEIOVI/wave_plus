<?php

declare(strict_types=1);

namespace App\Modules\Hr\Policies;

use App\Models\User;
use App\Modules\Hr\Models\Employee;

class EmployeePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('hr.employees.view')
            || $user->can('read-PeopleSkills')
            || $user->hasRole(['operations_responsible', 'Staff', 'Responsable']);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.view')
            || $user->can('read-PeopleSkills')
            || $employee->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employees.create')
            || $user->can('create-PeopleSkills');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.edit')
            || $user->can('edit-PeopleSkills');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.archive') || $user->can('delete-PeopleSkills');
    }
}
