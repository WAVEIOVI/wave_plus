<?php

declare(strict_types=1);

namespace App\Modules\Crm\Policies;

class PriceRequestPolicy extends CrmPolicy
{
    protected function subject(): string
    {
        return 'crm-price-requests';
    }
}
