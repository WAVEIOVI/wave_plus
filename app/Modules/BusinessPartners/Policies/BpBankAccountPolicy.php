<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Policies;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpBankAccount;
use Illuminate\Database\Eloquent\Model;

class BpBankAccountPolicy
{
    public function viewAny(User $user, ?Model $owner = null): bool
    {
        if (! ($user->can('read-bp-bank-accounts') || $user->can('manage-bp-bank-accounts'))) {
            return false;
        }

        if ($owner) {
            return $user->can('view', $owner);
        }

        return true;
    }

    public function view(User $user, BpBankAccount $bankAccount): bool
    {
        if (! ($user->can('read-bp-bank-accounts') || $user->can('manage-bp-bank-accounts'))) {
            return false;
        }

        $owner = $bankAccount->bankable;

        return $owner ? $user->can('view', $owner) : false;
    }

    public function create(User $user, ?Model $owner = null): bool
    {
        if (! $user->can('manage-bp-bank-accounts')) {
            return false;
        }

        if ($owner) {
            return $user->can('update', $owner);
        }

        return true;
    }

    public function update(User $user, BpBankAccount $bankAccount): bool
    {
        if (! $user->can('manage-bp-bank-accounts')) {
            return false;
        }

        $owner = $bankAccount->bankable;

        return $owner ? $user->can('update', $owner) : false;
    }

    public function delete(User $user, BpBankAccount $bankAccount): bool
    {
        if (! $user->can('manage-bp-bank-accounts')) {
            return false;
        }

        $owner = $bankAccount->bankable;

        return $owner ? $user->can('update', $owner) : false;
    }
}
