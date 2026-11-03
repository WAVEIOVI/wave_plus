<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

class UnitOfMeasurePolicy extends CatalogPolicy
{
    protected function subject(): string
    {
        return 'catalog-uom';
    }
}
