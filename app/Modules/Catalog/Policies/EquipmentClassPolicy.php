<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

class EquipmentClassPolicy extends CatalogPolicy
{
    protected function subject(): string
    {
        return 'catalog-equipment-classes';
    }
}
