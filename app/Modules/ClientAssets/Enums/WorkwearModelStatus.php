<?php

namespace App\Modules\ClientAssets\Enums;

enum WorkwearModelStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case DISCONTINUED = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::DISCONTINUED => 'Discontinued',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
