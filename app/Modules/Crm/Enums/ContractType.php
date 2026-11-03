<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum ContractType: string
{
    case Msa = 'msa';
    case Framework = 'framework';
}
