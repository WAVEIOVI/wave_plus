<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Enums;

enum DeliveryWaveStatus: string
{
    case Draft      = 'draft';
    case Planned    = 'planned';
    case Dispatched = 'dispatched';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Draft',
            self::Planned    => 'Planned',
            self::Dispatched => 'Dispatched',
            self::Completed  => 'Completed',
            self::Cancelled  => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft      => 'grey',
            self::Planned    => 'info',
            self::Dispatched => 'warning',
            self::Completed  => 'success',
            self::Cancelled  => 'grey-darken-1',
        };
    }
}
