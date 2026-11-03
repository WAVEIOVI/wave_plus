<?php

declare(strict_types=1);

namespace App\Modules\Crm\Policies;

class ContractPolicy extends CrmPolicy
{
    protected function subject(): string
    {
        return 'crm-contracts';
    }
}
