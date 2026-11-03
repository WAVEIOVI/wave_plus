<?php

namespace App\Modules\ClientAssets\Enums;

enum SiteType: string
{
    case OFFICE = 'office';
    case FACTORY = 'factory';
    case WAREHOUSE = 'warehouse';
    case CONSTRUCTION_SITE = 'construction_site';
    case RETAIL = 'retail';
    case HOSPITAL = 'hospital';
    case SCHOOL = 'school';
    case HOTEL = 'hotel';
    case RESTAURANT = 'restaurant';
    case RESIDENTIAL = 'residential';
    case GOVERNMENT = 'government';
    case INDUSTRIAL = 'industrial';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OFFICE => 'Office',
            self::FACTORY => 'Factory',
            self::WAREHOUSE => 'Warehouse',
            self::CONSTRUCTION_SITE => 'Construction Site',
            self::RETAIL => 'Retail',
            self::HOSPITAL => 'Hospital',
            self::SCHOOL => 'School',
            self::HOTEL => 'Hotel',
            self::RESTAURANT => 'Restaurant',
            self::RESIDENTIAL => 'Residential',
            self::GOVERNMENT => 'Government',
            self::INDUSTRIAL => 'Industrial',
            self::OTHER => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
