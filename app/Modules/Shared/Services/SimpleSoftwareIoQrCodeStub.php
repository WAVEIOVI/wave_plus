<?php

declare(strict_types=1);

namespace SimpleSoftwareIO\QrCode\Facades;

use App\Modules\Shared\Services\QrCodeService;

class QrCode
{
    public static function format(string $format = 'png'): QrCodeService
    {
        return QrCodeService::format($format);
    }

    public static function size(int $size): QrCodeService
    {
        return QrCodeService::format('png')->size($size);
    }

    public static function generateSvg(string $text): string
    {
        return QrCodeService::generateSvg($text);
    }

    public static function generate(string $text): string
    {
        return QrCodeService::generatePng($text);
    }
}
