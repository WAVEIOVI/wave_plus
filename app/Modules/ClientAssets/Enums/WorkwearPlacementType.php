<?php

namespace App\Modules\ClientAssets\Enums;

enum WorkwearPlacementType: string
{
    case LOGO = 'logo';
    case TEXT = 'text';
    case FLUO = 'fluo';
    case REFLECTIVE_BAND = 'reflective_band';
    case FLAG = 'flag';
    case BADGE = 'badge';
    case POCKET = 'pocket';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
