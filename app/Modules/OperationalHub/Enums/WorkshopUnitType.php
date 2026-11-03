<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

enum WorkshopUnitType: string
{
    case Fixed  = 'fixed';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Fixed  => 'Fixed Workshop',
            self::Mobile => 'Mobile Unit',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Fixed  => 'primary',
            self::Mobile => 'secondary',
        };
    }
}
