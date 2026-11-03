<?php

declare(strict_types=1);

namespace App\Modules\Crm\Policies;

class PriceListPolicy extends CrmPolicy
{
    protected function subject(): string
    {
        return 'crm-price-lists';
    }
}
