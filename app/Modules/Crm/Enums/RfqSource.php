<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum RfqSource: string
{
    case Phone = 'phone';
    case Email = 'email';
    case InPerson = 'in_person';
    case Portal = 'portal';
    case Tender = 'tender';
    case Referral = 'referral';
}
