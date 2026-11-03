<?php

declare(strict_types=1);

namespace App\Modules\Crm\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LsaDocumentPolicy extends CrmPolicy
{
    protected function subject(): string
    {
        return 'crm-lsa-documents';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
