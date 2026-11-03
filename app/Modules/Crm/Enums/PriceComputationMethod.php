<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum PriceComputationMethod: string
{
    case Fixed = 'fixed';
    case DiscountPct = 'discount_pct';
    case MarkupCost = 'markup_cost';
}
