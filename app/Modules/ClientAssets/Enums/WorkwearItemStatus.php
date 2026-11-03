<?php

namespace App\Modules\ClientAssets\Enums;

enum WorkwearItemStatus: string
{
    case IN_STOCK = 'in_stock';
    case ASSIGNED = 'assigned';
    case RETURNED = 'returned';
    case AVAILABLE = 'available';
    case REPAIR_NEEDED = 'repair_needed';
    case RETIRED = 'retired';
    case LOST = 'lost';
    case STOLEN = 'stolen';

    public function label(): string
    {
        return match ($this) {
            self::IN_STOCK => 'In Stock',
            self::ASSIGNED => 'Assigned',
            self::RETURNED => 'Returned',
            self::AVAILABLE => 'Available',
            self::REPAIR_NEEDED => 'Repair Needed',
            self::RETIRED => 'Retired',
            self::LOST => 'Lost',
            self::STOLEN => 'Stolen',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses that make an item eligible for assignment.
     */
    public static function assignableStatuses(): array
    {
        return [
            self::IN_STOCK->value,
            self::AVAILABLE->value,
        ];
    }

    /**
     * Valid transitions from the current status.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::IN_STOCK => [self::ASSIGNED, self::RETIRED, self::LOST, self::STOLEN],
            self::ASSIGNED => [self::RETURNED],
            self::RETURNED => [self::AVAILABLE, self::REPAIR_NEEDED, self::RETIRED, self::LOST, self::STOLEN],
            self::AVAILABLE => [self::ASSIGNED, self::RETIRED, self::LOST, self::STOLEN],
            self::REPAIR_NEEDED => [self::AVAILABLE, self::RETIRED],
            self::RETIRED => [],
            self::LOST => [],
            self::STOLEN => [],
        };
    }

    /**
     * Check whether a transition to the given status is valid.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
