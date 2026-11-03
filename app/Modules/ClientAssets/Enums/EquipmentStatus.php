<?php

namespace App\Modules\ClientAssets\Enums;

enum EquipmentStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNDER_MAINTENANCE = 'under_maintenance';
    case DECOMMISSIONED = 'decommissioned';
    case LOST = 'lost';
    case STOLEN = 'stolen';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::UNDER_MAINTENANCE => 'Under Maintenance',
            self::DECOMMISSIONED => 'Decommissioned',
            self::LOST => 'Lost',
            self::STOLEN => 'Stolen',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
