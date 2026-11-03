<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Policies;

class RfqHeaderPolicy extends ProcurementPolicy
{
    protected function subject(): string
    {
        return 'procurement-rfqs';
    }

    public function award($user, $record): bool
    {
        return $user->can('award-procurement-rfqs');
    }
}
