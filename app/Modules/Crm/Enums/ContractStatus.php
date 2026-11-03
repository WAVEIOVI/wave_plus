<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Negotiation = 'negotiation';
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Renewed = 'renewed';
    case Terminated = 'terminated';
    case Expired = 'expired';
}
