<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum RfqStatus: string
{
    case New = 'new';
    case PendingInternal = 'pending_internal';
    case Costing = 'costing';
    case Responded = 'responded';
    case ConvertedToQuote = 'converted_to_quote';
    case Cancelled = 'cancelled';
}
