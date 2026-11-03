<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum PaymentTerms: string
{
    case Immediate = 'immediate';
    case Net15 = 'net_15';
    case Net30 = 'net_30';
    case Net60 = 'net_60';
    case Advance50 = 'advance_50';
    case Split = 'split';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';
    case PerOrder = 'per_order';
}
