<?php

declare(strict_types=1);

namespace App\Modules\Hr\Enums;

enum VehicleStatus: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';
    case RentalActive = 'rental_active';
    case ClientUse = 'client_use';
    case Retired = 'retired';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::InUse => 'In Use',
            self::Maintenance => 'Under Maintenance',
            self::OutOfService => 'Out of Service',
            self::RentalActive => 'Rental Active',
            self::ClientUse => 'Client Use',
            self::Retired => 'Retired',
            self::Sold => 'Sold',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
