<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Enums;

enum DwoStatus: string
{
    case Draft              = 'draft';
    case Planned            = 'planned';
    case ReadyToPick        = 'ready_to_pick';
    case Picking            = 'picking';
    case ReadyToLoad        = 'ready_to_load';
    case Loaded             = 'loaded';
    case Dispatched         = 'dispatched';
    case InTransit          = 'in_transit';
    case Delivered          = 'delivered';
    case Closed             = 'closed';
    case Cancelled          = 'cancelled';
    case DeliveryFailed     = 'delivery_failed';
    case PartiallyDelivered = 'partially_delivered';
    case Exception          = 'exception';

    public function label(): string
    {
        return match ($this) {
            self::Draft              => 'Draft',
            self::Planned            => 'Planned',
            self::ReadyToPick        => 'Ready to Pick',
            self::Picking            => 'Picking',
            self::ReadyToLoad        => 'Ready to Load',
            self::Loaded             => 'Loaded',
            self::Dispatched         => 'Dispatched',
            self::InTransit          => 'In Transit',
            self::Delivered          => 'Delivered',
            self::Closed             => 'Closed',
            self::Cancelled          => 'Cancelled',
            self::DeliveryFailed     => 'Delivery Failed',
            self::PartiallyDelivered => 'Partially Delivered',
            self::Exception          => 'Exception',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft              => 'grey',
            self::Planned            => 'blue-grey',
            self::ReadyToPick        => 'indigo',
            self::Picking            => 'cyan',
            self::ReadyToLoad        => 'amber',
            self::Loaded             => 'orange',
            self::Dispatched         => 'deep-orange',
            self::InTransit          => 'purple',
            self::Delivered          => 'teal',
            self::Closed             => 'success',
            self::Cancelled          => 'grey-darken-1',
            self::DeliveryFailed     => 'error',
            self::PartiallyDelivered => 'warning',
            self::Exception          => 'deep-orange-darken-2',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [
                self::Planned,
                self::Cancelled,
            ],
            self::Planned => [
                self::ReadyToPick,
                self::Draft,
                self::Cancelled,
            ],
            self::ReadyToPick => [
                self::Picking,
                self::Planned,
                self::Cancelled,
            ],
            self::Picking => [
                self::ReadyToLoad,
                self::ReadyToPick,
                self::Exception,
                self::Cancelled,
            ],
            self::ReadyToLoad => [
                self::Loaded,
                self::Picking,
                self::Cancelled,
            ],
            self::Loaded => [
                self::Dispatched,
                self::ReadyToLoad,
                self::Cancelled,
            ],
            self::Dispatched => [
                self::InTransit,
                self::Exception,
            ],
            self::InTransit => [
                self::Delivered,
                self::PartiallyDelivered,
                self::DeliveryFailed,
                self::Exception,
            ],
            self::Delivered => [
                self::Closed,
                self::Exception,
            ],
            self::PartiallyDelivered => [
                self::Closed,
                self::Exception,
            ],
            self::DeliveryFailed => [
                self::Planned, // Re-schedule
                self::Cancelled,
                self::Closed,
            ],
            self::Exception => [
                self::InTransit,
                self::Delivered,
                self::PartiallyDelivered,
                self::DeliveryFailed,
                self::Cancelled,
                self::Picking,
                self::Loaded,
            ],
            self::Closed => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
