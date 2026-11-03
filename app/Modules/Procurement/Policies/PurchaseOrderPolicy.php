<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Policies;

class PurchaseOrderPolicy extends ProcurementPolicy
{
    protected function subject(): string
    {
        return 'procurement-pos';
    }
}
