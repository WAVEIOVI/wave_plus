<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Policies;

class SupplierPolicy extends PartnerPolicy
{
    protected function subject(): string
    {
        return 'suppliers';
    }
}
