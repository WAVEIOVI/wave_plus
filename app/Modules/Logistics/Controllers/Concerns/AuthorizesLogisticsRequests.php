<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers\Concerns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait AuthorizesLogisticsRequests
{
    use AuthorizesRequests;
}
