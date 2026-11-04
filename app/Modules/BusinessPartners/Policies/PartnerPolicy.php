<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class PartnerPolicy
{
    abstract protected function subject(): string;

    public function viewAny(User $user): bool
    {
        return $user->can('read-'.$this->subject());
    }

    public function view(User $user, Model $partner): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-'.$this->subject());
    }

    public function update(User $user, Model $partner): bool
    {
        return $user->can('edit-'.$this->subject());
    }

    public function delete(User $user, Model $partner): bool
    {
        return $user->can('delete-'.$this->subject());
    }

    public function export(User $user, ?Model $partner = null): bool
    {
        return $user->can('export-'.$this->subject());
    }
}
