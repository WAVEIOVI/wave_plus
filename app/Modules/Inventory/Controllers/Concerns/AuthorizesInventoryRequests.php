<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers\Concerns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait AuthorizesInventoryRequests
{
    use AuthorizesRequests;
}
