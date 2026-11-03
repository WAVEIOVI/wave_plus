<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers\Concerns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait AuthorizesCatalogRequests
{
    use AuthorizesRequests;
}
