<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class ClientAssetsPolicy
{
    abstract protected function subject(): string;

    public function viewAny(User $user): bool
    {
        return $user->can('read-'.$this->subject())
            || $user->can('read-ClientAssets');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-'.$this->subject());
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('edit-'.$this->subject());
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('delete-'.$this->subject());
    }
}
