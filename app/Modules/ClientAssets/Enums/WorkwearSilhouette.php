<?php

namespace App\Modules\ClientAssets\Enums;

/**
 * Garment illustration silhouettes available in the Workwear Model studio.
 */
enum WorkwearSilhouette: string
{
    case COVERALL = 'coverall';
    case OVERALLS = 'overalls';
    case JACKET = 'jacket';
    case PARKA = 'parka';
    case VEST = 'vest';
    case POLO = 'polo';
    case TROUSERS = 'trousers';
    case APRON = 'apron';
    case BOOTS = 'boots';
    case GLOVES = 'gloves';
    case HELMET = 'helmet';
    case GLASSES = 'glasses';
    case HARNESS = 'harness';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
