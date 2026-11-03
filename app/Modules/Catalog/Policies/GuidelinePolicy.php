<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

class GuidelinePolicy extends CatalogPolicy
{
    protected function subject(): string
    {
        return 'catalog-guidelines';
    }
}
