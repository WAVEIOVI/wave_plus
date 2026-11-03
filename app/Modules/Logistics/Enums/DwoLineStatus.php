<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Enums;

enum DwoLineStatus: string
{
    case Pending            = 'pending';
    case Picked             = 'picked';
    case Loaded             = 'loaded';
    case Dispatched         = 'dispatched';
    case Delivered          = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Returned           = 'returned';
    case Failed             = 'failed';
    case Cancelled          = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending            => 'Pending',
            self::Picked             => 'Picked',
            self::Loaded             => 'Loaded',
            self::Dispatched         => 'Dispatched',
            self::Delivered          => 'Delivered',
            self::PartiallyDelivered => 'Partially Delivered',
            self::Returned           => 'Returned',
            self::Failed             => 'Failed',
            self::Cancelled          => 'Cancelled',
        };
    }
}
