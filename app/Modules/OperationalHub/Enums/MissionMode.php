<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

/**
 * Mission execution modes.
 *
 * Mirrors {@see SwoFulfillmentMode}: only field_service and workshop exist.
 * A field-service mission may use a vehicle configured as a mobile workshop.
 */
enum MissionMode: string
{
    case FieldService = 'field_service';
    case Workshop     = 'workshop';

    public function label(): string
    {
        return match ($this) {
            self::FieldService => 'Field Service',
            self::Workshop     => 'Workshop',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::FieldService => 'primary',
            self::Workshop     => 'info',
        };
    }

    public function isWorkshop(): bool
    {
        return $this === self::Workshop;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $mode) => $mode->value, self::cases());
    }
}
