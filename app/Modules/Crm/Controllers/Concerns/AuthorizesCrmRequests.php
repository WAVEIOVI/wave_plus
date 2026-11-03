<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers\Concerns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait AuthorizesCrmRequests
{
    use AuthorizesRequests;
}
