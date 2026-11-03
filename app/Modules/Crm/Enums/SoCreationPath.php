<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum SoCreationPath: string
{
    case Manual          = 'manual';
    case Quotation       = 'quotation';
    case ContractCallOff = 'contract_call_off';
}
