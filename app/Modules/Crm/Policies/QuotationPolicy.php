<?php

declare(strict_types=1);

namespace App\Modules\Crm\Policies;

class QuotationPolicy extends CrmPolicy
{
    protected function subject(): string
    {
        return 'crm-quotations';
    }
}
