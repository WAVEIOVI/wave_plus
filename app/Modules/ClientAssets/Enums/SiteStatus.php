<?php

namespace App\Modules\ClientAssets\Enums;

enum SiteStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNDER_CONSTRUCTION = 'under_construction';
    case DECOMMISSIONED = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::UNDER_CONSTRUCTION => 'Under Construction',
            self::DECOMMISSIONED => 'Decommissioned',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
