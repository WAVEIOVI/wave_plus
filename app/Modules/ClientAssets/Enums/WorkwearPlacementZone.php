<?php

namespace App\Modules\ClientAssets\Enums;

/**
 * Anchor zones for customization placements on the garment illustration.
 * The pixel anchor of each zone is resolved by the front-end illustration; the
 * backend only guarantees that stored zones are known, stable identifiers.
 */
enum WorkwearPlacementZone: string
{
    case CHEST_LEFT = 'chest_left';
    case CHEST_RIGHT = 'chest_right';
    case CHEST_CENTER = 'chest_center';
    case POCKET_LEFT = 'pocket_left';
    case POCKET_RIGHT = 'pocket_right';
    case SHOULDER_LEFT = 'shoulder_left';
    case SHOULDER_RIGHT = 'shoulder_right';
    case COLLAR = 'collar';
    case SLEEVE_LEFT = 'sleeve_left';
    case SLEEVE_RIGHT = 'sleeve_right';
    case WAIST = 'waist';
    case HEM = 'hem';
    case THIGH_LEFT = 'thigh_left';
    case THIGH_RIGHT = 'thigh_right';
    case KNEE_LEFT = 'knee_left';
    case KNEE_RIGHT = 'knee_right';
    case BACK_UPPER = 'back_upper';
    case BACK_CENTER = 'back_center';
    case BACK_LOWER = 'back_lower';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
