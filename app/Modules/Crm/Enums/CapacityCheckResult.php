<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum CapacityCheckResult: string
{
    case Available = 'available';
    case Constrained = 'constrained';
    case Unavailable = 'unavailable';
}
