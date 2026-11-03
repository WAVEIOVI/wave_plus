<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Controllers\Concerns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait AuthorizesProcurementRequests
{
    use AuthorizesRequests;
}
