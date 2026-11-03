<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FinancePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin') || $user->hasRole('Finance Manager')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('finance.view') || $user->can('finance.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('finance.view') || $user->can('finance.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.create') || $user->can('finance.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('finance.edit') || $user->can('finance.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('finance.delete') || $user->can('finance.manage');
    }

    public function post(User $user): bool
    {
        return $user->can('finance.post') || $user->can('finance.manage');
    }

    public function closePeriod(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->hasRole('Finance Manager');
    }
}
