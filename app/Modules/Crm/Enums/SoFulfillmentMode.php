<?php

declare(strict_types=1);

namespace App\Modules\Crm\Enums;

/**
 * Requested/preferred fulfillment method for a Sales Order.
 *
 * This is NOT the final operational execution record.
 * The downstream DWO/SWO defines or overrides the actual execution method.
 * Do not mix commercial requested mode with operational execution mode.
 *
 * Sales / Goods SO:
 *   own_delivery | third_party_delivery | customer_pickup
 *
 * Service SO:
 *   field_service | workshop
 *
 * field_service = mobile workshop / vehicle-based field execution.
 * MobileWorkshop is retained for legacy data only and maps to field_service.
 */
enum SoFulfillmentMode: string
{
    // Sales / Goods
    case OwnDelivery       = 'own_delivery';
    case ThirdPartyDelivery = 'third_party_delivery';
    case CustomerPickup    = 'customer_pickup';

    // Service (canonical)
    case FieldService   = 'field_service';
    case Workshop       = 'workshop';

    /** @deprecated Use FieldService — kept for legacy stored values */
    case MobileWorkshop = 'mobile_workshop';

    /**
     * Returns the valid modes for a given SO type string.
     *
     * @param  string  $soType  'sales' | 'service'
     * @return self[]
     */
    public static function forSoType(string $soType): array
    {
        return match ($soType) {
            'sales'   => [self::OwnDelivery, self::ThirdPartyDelivery, self::CustomerPickup],
            'service' => [self::FieldService, self::Workshop],
            default   => [],
        };
    }

    /**
     * Returns string values valid for a given SO type.
     *
     * @param  string  $soType
     * @return string[]
     */
    public static function valuesForSoType(string $soType): array
    {
        return array_map(fn (self $m) => $m->value, self::forSoType($soType));
    }

    /**
     * Checks if a given fulfillment mode value is valid for the specified SO type.
     */
    public static function isValidForSoType(string $mode, string $soType): bool
    {
        return in_array($mode, self::valuesForSoType($soType), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::OwnDelivery        => 'Own Delivery',
            self::ThirdPartyDelivery => 'Third-Party Delivery',
            self::CustomerPickup     => 'Customer Pickup',
            self::FieldService       => 'Field Service',
            self::Workshop           => 'Workshop',
            self::MobileWorkshop     => 'Mobile Workshop',
        };
    }
}
