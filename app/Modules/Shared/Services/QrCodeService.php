<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    private string $format = 'png';

    private int $size = 300;

    /**
     * Fluent API entry point for format.
     * Supported formats: 'png' (default), 'svg'.
     */
    public static function format(string $format = 'png'): self
    {
        $instance = new self;
        $instance->format = strtolower($format);

        return $instance;
    }

    /**
     * Fluent API for size in pixels (applies to PNG only; SVG is resolution-independent).
     */
    public function size(int $size): self
    {
        $this->size = max(50, $size);

        return $this;
    }

    /**
     * Generate output for text using fluent configuration.
     * Returns raw PNG bytes for 'png', or an SVG string for 'svg'.
     */
    public function generate(string $text): string
    {
        return match ($this->format) {
            'svg' => self::generateSvg($text),
            default => self::generatePng($text, $this->size),
        };
    }

    /**
     * Generate raw binary PNG QR code bytes for a given string or URL.
     */
    public static function generatePng(string $text, int $size = 300): string
    {
        $scale = max(2, (int) round($size / 41));

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'scale' => $scale,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
        ]);

        return (new QRCode($options))->render($text);
    }

    /**
     * Generate an SVG string for a given text payload.
     * SVG is resolution-independent and suitable for print-quality badges,
     * label printing, and responsive display contexts.
     * The XML declaration is omitted so the output embeds cleanly in HTML.
     */
    public static function generateSvg(string $text): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'svgAddXmlHeader' => false,
        ]);

        return (new QRCode($options))->render($text);
    }
}
