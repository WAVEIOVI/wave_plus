<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Enums;

enum DeliveryCondition: string
{
    case Good    = 'good';
    case Damaged = 'damaged';
    case Partial = 'partial';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Good    => 'Good Condition',
            self::Damaged => 'Damaged',
            self::Partial => 'Partial Delivery',
            self::Refused => 'Refused Delivery',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Good    => 'success',
            self::Damaged => 'error',
            self::Partial => 'warning',
            self::Refused => 'deep-orange',
        };
    }
}
