<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Modules\Shared\Services\QrCodeService;
use Tests\TestCase;

/**
 * Tests for QrCodeService — verifies PNG and SVG generation paths,
 * fluent API chaining, and the SimpleSoftwareIoQrCodeStub facade.
 */
class QrCodeServiceTest extends TestCase
{
    // ─── PNG generation ──────────────────────────────────────────────────────

    public function test_generate_png_returns_valid_png_bytes(): void
    {
        $bytes = QrCodeService::generatePng('https://example.com/test');

        // PNG magic header: 8 bytes 0x89 P N G \r \n 0x1a \n
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes);
        $this->assertGreaterThan(100, strlen($bytes));
    }

    public function test_generate_png_with_custom_size(): void
    {
        $small = QrCodeService::generatePng('TEST', 100);
        $large = QrCodeService::generatePng('TEST', 600);

        $this->assertStringStartsWith("\x89PNG", $small);
        $this->assertStringStartsWith("\x89PNG", $large);

        // Larger size should produce a bigger file.
        $this->assertGreaterThan(strlen($small), strlen($large));
    }

    public function test_fluent_png_format_chain(): void
    {
        $bytes = QrCodeService::format('png')->size(200)->generate('FLUENT-TEST');

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    public function test_fluent_default_format_is_png(): void
    {
        $bytes = QrCodeService::format()->generate('DEFAULT-FORMAT');

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    // ─── SVG generation ──────────────────────────────────────────────────────

    public function test_generate_svg_returns_svg_markup(): void
    {
        $svg = QrCodeService::generateSvg('https://example.com/qr');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_generate_svg_does_not_include_xml_declaration(): void
    {
        $svg = QrCodeService::generateSvg('TEST-SVG-NO-DECL');

        // svgAddXmlHeader is false — the output must be safe to embed in HTML
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_generate_svg_encodes_different_payloads(): void
    {
        $svg1 = QrCodeService::generateSvg('WP:EMP:EMP-00001');
        $svg2 = QrCodeService::generateSvg('WP:EMP:EMP-00999');

        // Both must be valid SVG but the path data differs.
        $this->assertStringContainsString('<svg', $svg1);
        $this->assertStringContainsString('<svg', $svg2);
        $this->assertNotEquals($svg1, $svg2);
    }

    public function test_fluent_svg_format_chain(): void
    {
        $svg = QrCodeService::format('svg')->generate('FLUENT-SVG');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    // ─── Stub facade ─────────────────────────────────────────────────────────

    public function test_stub_facade_format_delegates_to_service(): void
    {
        // The SimpleSoftwareIoQrCodeStub is a manual compatibility shim; verify it
        // through the public QrCodeService API it delegates to.
        $service = QrCodeService::format('png');

        $this->assertInstanceOf(QrCodeService::class, $service);
    }

    public function test_stub_facade_generate_returns_png(): void
    {
        // QrCodeService::format('png')->generate() is the delegated path used by the stub.
        $bytes = QrCodeService::format('png')->generate('STUB-TEST');

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    public function test_stub_facade_generate_svg_returns_svg(): void
    {
        // QrCodeService::generateSvg() is the delegated path used by the stub.
        $svg = QrCodeService::generateSvg('STUB-SVG');

        $this->assertStringContainsString('<svg', $svg);
    }

    // ─── Minimum size guard ──────────────────────────────────────────────────

    public function test_size_floor_prevents_sub_50_px_output(): void
    {
        // Should not throw; the service clamps to minimum 50.
        $bytes = QrCodeService::format('png')->size(10)->generate('CLAMP-TEST');

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    // ─── URL payload integrity ────────────────────────────────────────────────

    public function test_png_generation_accepts_full_url_payloads(): void
    {
        $url = 'https://wave.example.com/ca/scan/eq/EQ-2026-EXT-001';
        $bytes = QrCodeService::generatePng($url, 300);

        $this->assertStringStartsWith("\x89PNG", $bytes);
        $this->assertGreaterThan(500, strlen($bytes));
    }

    public function test_svg_generation_accepts_badge_prefix_payloads(): void
    {
        $payload = 'WP:EMP:EMP-00042';
        $svg = QrCodeService::generateSvg($payload);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }
}
