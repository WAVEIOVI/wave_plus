<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Enums;

enum DeliveryStopStatus: string
{
    case Pending   = 'pending';
    case Arrived   = 'arrived';
    case Completed = 'completed';
    case Skipped   = 'skipped';
    case Failed    = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Arrived   => 'Arrived',
            self::Completed => 'Completed',
            self::Skipped   => 'Skipped',
            self::Failed    => 'Failed',
        };
    }
}
