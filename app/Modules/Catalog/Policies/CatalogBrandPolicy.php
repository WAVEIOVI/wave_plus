<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

class CatalogBrandPolicy extends CatalogPolicy
{
    protected function subject(): string
    {
        return 'catalog-brands';
    }
}
