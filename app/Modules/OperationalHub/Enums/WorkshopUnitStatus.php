<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

enum WorkshopUnitStatus: string
{
    case Active      = 'active';
    case Inactive    = 'inactive';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Active      => 'Active',
            self::Inactive    => 'Inactive',
            self::Maintenance => 'Maintenance',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active      => 'success',
            self::Inactive    => 'secondary',
            self::Maintenance => 'warning',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }
}
