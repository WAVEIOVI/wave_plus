<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

enum DocumentStatus: string
{
    case Generated = 'generated';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Disputed = 'disputed';
}
