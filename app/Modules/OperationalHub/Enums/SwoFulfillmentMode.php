<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

/**
 * Service Work Order fulfillment modes.
 *
 * Only TWO fulfillment modes exist:
 *  - field_service : work executed at the client site. The field crew may use a
 *                    normal service vehicle OR a vehicle configured as a mobile
 *                    workshop. A "mobile workshop" is a field-service execution
 *                    configuration (vehicle), NOT a third fulfillment mode.
 *  - workshop      : work executed inside a workshop unit.
 */
enum SwoFulfillmentMode: string
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

    public function isFieldService(): bool
    {
        return $this === self::FieldService;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $mode) => $mode->value, self::cases());
    }
}
