<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum StockCheckResult: string
{
    case Sufficient = 'sufficient';
    case Partial = 'partial';
    case Insufficient = 'insufficient';
}
