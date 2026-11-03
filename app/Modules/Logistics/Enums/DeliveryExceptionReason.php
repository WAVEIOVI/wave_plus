<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Enums;

enum DeliveryExceptionReason: string
{
    case CustomerUnavailable = 'customer_unavailable';
    case CustomerRefused     = 'customer_refused';
    case DamagedGoods        = 'damaged_goods';
    case ShortDelivery       = 'short_delivery';
    case WrongItem           = 'wrong_item';
    case PartialAcceptance   = 'partial_acceptance';
    case AddressProblem      = 'address_problem';
    case VehicleIssue        = 'vehicle_issue';
    case Other               = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerUnavailable => 'Customer Unavailable',
            self::CustomerRefused     => 'Customer Refused',
            self::DamagedGoods        => 'Damaged Goods',
            self::ShortDelivery       => 'Short Delivery',
            self::WrongItem           => 'Wrong Item Delivered',
            self::PartialAcceptance   => 'Partial Acceptance',
            self::AddressProblem      => 'Address Problem / Inaccessible',
            self::VehicleIssue        => 'Vehicle Breakdown / Issue',
            self::Other               => 'Other Reason',
        };
    }
}
