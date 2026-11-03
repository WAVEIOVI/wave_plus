<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Controllers\Concerns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait AuthorizesClientAssetsRequests
{
    use AuthorizesRequests;
}
