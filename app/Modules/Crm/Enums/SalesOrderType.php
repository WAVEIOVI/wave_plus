<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum SalesOrderType: string
{
    case Sales = 'sales';
    case Service = 'service';
}
