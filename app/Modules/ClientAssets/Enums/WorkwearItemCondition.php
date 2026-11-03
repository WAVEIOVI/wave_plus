<?php

namespace App\Modules\ClientAssets\Enums;

enum WorkwearItemCondition: string
{
    case NEW = 'new';
    case GOOD = 'good';
    case FAIR = 'fair';
    case WORN = 'worn';
    case DAMAGED = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::GOOD => 'Good',
            self::FAIR => 'Fair',
            self::WORN => 'Worn',
            self::DAMAGED => 'Damaged',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
