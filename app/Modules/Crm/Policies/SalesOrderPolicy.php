<?php

declare(strict_types=1);

namespace App\Modules\Crm\Policies;

class SalesOrderPolicy extends CrmPolicy
{
    protected function subject(): string
    {
        return 'crm-sales-orders';
    }
}
