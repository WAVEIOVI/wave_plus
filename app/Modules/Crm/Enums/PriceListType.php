<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum PriceListType: string
{
    case Standard = 'standard';
    case Segment = 'segment';
    case ClientSpecific = 'client_specific';
    case Promo = 'promo';
    case Volume = 'volume';
}
