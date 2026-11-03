<?php

namespace App\Modules\ClientAssets\Enums;

enum EmployeeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::TERMINATED => 'Terminated',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
