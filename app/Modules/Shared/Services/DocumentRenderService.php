<?php

namespace App\Modules\Shared\Services;

use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared document rendering helpers.
 *
 * This service owns:
 *  - Browsershot configuration (reads from config/browsershot.php, falls back to config/services.php)
 *  - The ONE page-geometry / margin strategy for every generated PDF (A4 portrait + landscape)
 *  - The repeating branded header + footer (rendered by Chrome as native header/footer templates,
 *    so they repeat on every page and can never overlap the body content)
 *  - Streaming CSV responses with correct MIME type, UTF-8 BOM, and header row
 *
 * Margin strategy (single source of truth, see PAGE_GEOMETRY):
 *  - Browsershot/Chrome margins define the printable content area. The header lives in the top margin,
 *    the footer in the bottom margin. Blade layouts therefore use `@page { size: A4 <orientation>; }`
 *    WITHOUT any @page margin, WITHOUT position:fixed header/footer and WITHOUT body padding for them.
 *  - Header/footer templates are full-page-width boxes, so they re-apply the same left/right margin as padding.
 *
 * Module controllers remain responsible for data loading, authorization, and view rendering.
 * Do NOT place module-specific business logic here.
 *
 * Blade layouts communicate with this service through <meta name="wp-pdf-*"> tags
 * (title, orientation, badge, ref, footer-note, footer-address, footer-tax-id, footer-phone).
 */
class DocumentRenderService
{
    public const ORIENTATION_PORTRAIT = 'portrait';

    public const ORIENTATION_LANDSCAPE = 'landscape';

    /**
     * Chrome insets header/footer templates ~5.3mm from the physical page edge (measured on rendered PDFs),
     * so template zones are (margin - inset) tall. TEMPLATE_GAP_MM is breathing room against the body.
     */
    public const TEMPLATE_INSET_MM = 5.3;

    public const TEMPLATE_GAP_MM = 3;

    /**
     * Page geometry in millimetres. margins = [top, right, bottom, left].
     * Top/bottom margins host the header/footer templates; left/right are the content gutters.
     *
     * @var array<string, array{margins: array<int, float>}>
     */
    public const PAGE_GEOMETRY = [
        self::ORIENTATION_PORTRAIT => ['margins' => [28, 15, 22, 15]],
        self::ORIENTATION_LANDSCAPE => ['margins' => [26, 12, 20, 12]],
    ];

    /** @var array<string, string> */
    private static array $logoCache = [];

    /**
     * Create a pre-configured Browsershot instance ready for PDF generation.
     *
     * Orientation is taken from the argument, else from the layout's wp-pdf-orientation meta, else portrait.
     *
     * Config key resolution order (first non-null wins):
     *   1. config('browsershot.chrome_path')     — config/browsershot.php
     *   2. config('services.browsershot.chrome_path') — config/services.php legacy key
     */
    public function makeBrowsershot(string $html, ?string $orientation = null): Browsershot
    {
        $meta = $this->extractMeta($html);
        $orientation = $this->resolveOrientation($orientation ?? ($meta['orientation'] ?? null));
        [$top, $right, $bottom, $left] = self::PAGE_GEOMETRY[$orientation]['margins'];

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->margins($top, $right, $bottom, $left)
            ->showBackground()
            ->waitUntilNetworkIdle()
            // Header/footer templates embed base64 logos; pass options via a temp file so the
            // command line never exceeds OS limits (Windows crashes well below the logo size).
            ->writeOptionsToFile()
            ->showBrowserHeaderAndFooter()
            ->headerHtml($this->buildHeaderHtml($meta, $left, $right, $top))
            ->footerHtml($this->buildFooterHtml($meta, $left, $right, $bottom));

        if ($orientation === self::ORIENTATION_LANDSCAPE) {
            $browsershot->landscape();
        }

        $chromePath = config('browsershot.chrome_path') ?? config('services.browsershot.chrome_path');
        if ($chromePath) {
            $browsershot->setChromePath($chromePath);
        }

        $nodePath = config('browsershot.node_path') ?? config('services.browsershot.node_path');
        if ($nodePath) {
            $browsershot->setNodeBinary($nodePath);
        }

        $npmPath = config('browsershot.npm_path') ?? config('services.browsershot.npm_path');
        if ($npmPath) {
            $browsershot->setNpmBinary($npmPath);
        }

        return $browsershot;
    }

    /**
     * Render HTML to raw PDF bytes (for jobs / attachments that need the content, not a response).
     */
    public function renderPdf(string $html, ?string $orientation = null): string
    {
        return $this->makeBrowsershot($html, $orientation)->pdf();
    }

    /**
     * Stream a CSV download response.
     *
     * Outputs a UTF-8 BOM so Excel opens the file without encoding issues.
     * Uses PHP's native fputcsv — no third-party package required.
     *
     * @param  string  $filename  e.g. 'clients-2024-01-01.csv'
     * @param  string[]  $headers  Column header labels, in order
     * @param  iterable<array<int|string, mixed>>  $rows  Each row as a sequential array matching $headers
     */
    public function streamCsvResponse(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $safeFilename = $this->sanitizeFilename($filename, 'csv');

        return response()->stream(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');

            // UTF-8 BOM — required for correct Excel rendering
            fwrite($output, "\xEF\xBB\xBF");

            // Header row
            fputcsv($output, $headers);

            // Data rows
            foreach ($rows as $row) {
                fputcsv($output, array_values((array) $row));
            }

            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Build a PDF download response from rendered HTML.
     *
     * Orientation: explicit $landscape wins; null lets the Blade layout decide (wp-pdf-orientation meta).
     * Margins are owned by PAGE_GEOMETRY so header/footer templates never collide with body content;
     * the optional $margins parameter is retained solely for backward-compatible call signatures.
     *
     * @param  array<int, float|int>|null  $margins
     */
    public function renderPdfResponse(
        string $html,
        string $filename,
        ?bool $landscape = null,
        ?array $margins = null
    ): \Illuminate\Http\Response {
        $safeFilename = $this->sanitizeFilename($filename, 'pdf');

        $orientation = $landscape === null
            ? null
            : ($landscape ? self::ORIENTATION_LANDSCAPE : self::ORIENTATION_PORTRAIT);

        $pdf = $this->makeBrowsershot($html, $orientation)->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header / footer templates
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Read the <meta name="wp-pdf-*"> values a layout exposes.
     *
     * @return array<string, string>
     */
    public function extractMeta(string $html): array
    {
        $meta = [];

        if (preg_match_all('/<meta\s+name=["\']wp-pdf-([a-z-]+)["\']\s+content=(?:"([^"]*)"|\'([^\']*)\')/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $raw = $match[2] !== '' ? $match[2] : ($match[3] ?? '');
                $value = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($value !== '') {
                    $meta[strtolower($match[1])] = $value;
                }
            }
        }

        return $meta;
    }

    private function resolveOrientation(?string $orientation): string
    {
        return strtolower((string) $orientation) === self::ORIENTATION_LANDSCAPE
            ? self::ORIENTATION_LANDSCAPE
            : self::ORIENTATION_PORTRAIT;
    }

    /**
     * Header zone: Chrome places header/footer templates inset from the page edge (TEMPLATE_INSET_MM),
     * so the usable template height is (margin - inset). A further TEMPLATE_GAP_MM keeps the header rule
     * clear of the body content.
     *
     * @param  array<string, string>  $meta
     */
    private function buildHeaderHtml(array $meta, float $left, float $right, float $top): string
    {
        $zone = max($top - self::TEMPLATE_INSET_MM - self::TEMPLATE_GAP_MM, 10);
        $title = $this->e($meta['title'] ?? 'Document');
        $badge = isset($meta['badge']) ? '<span class="wp-hdr-badge">'.$this->e($meta['badge']).'</span>' : '';
        $ref = isset($meta['ref']) ? '<div class="wp-hdr-ref">'.$this->e($meta['ref']).'</div>' : '';
        $generatedAt = $this->e(now()->format('d/m/Y · H:i'));
        $logo = $this->logoDataUri('ss-plus');
        $logoTag = $logo !== ''
            ? '<img class="wp-hdr-logo" src="'.$logo.'" alt="SS PLUS">'
            : '<strong class="wp-hdr-logo-fallback">SS PLUS</strong>';

        return <<<HTML
<style>
  html, body { margin: 0; padding: 0; }
  * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .wp-hdr {
    width: 100%;
    height: {$zone}mm;
    padding: 0 {$right}mm 0 {$left}mm;
    font-family: "Segoe UI", Arial, Helvetica, sans-serif;
    color: #0f172a;
  }
  .wp-hdr-bar {
    width: 100%;
    height: 100%;
    padding-bottom: 2.2mm;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 6mm;
    border-bottom: 0.5mm solid #0d9488;
  }
  .wp-hdr-brand {
    display: flex;
    align-items: center;
    gap: 3.2mm;
    min-width: 0;
  }
  .wp-hdr-logo { height: 10.5mm; width: auto; display: block; flex: 0 0 auto; }
  .wp-hdr-logo-fallback { font-size: 14px; font-weight: 800; letter-spacing: 0.8px; color: #0f172a; }
  .wp-hdr-divider {
    width: 0.25mm;
    height: 8mm;
    background: #cbd5e1;
    flex: 0 0 auto;
  }
  .wp-hdr-company {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
  }
  .wp-hdr-company-name {
    font-size: 9.5px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.1px;
  }
  .wp-hdr-company-sub {
    font-size: 7.2px;
    color: #64748b;
  }
  .wp-hdr-meta-col {
    text-align: right;
    min-width: 0;
    line-height: 1.35;
    flex: 0 1 auto;
  }
  .wp-hdr-doc-title {
    font-size: 11px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.15px;
  }
  .wp-hdr-ref {
    font-size: 8.2px;
    color: #334155;
    font-weight: 600;
    font-family: Consolas, "Courier New", monospace;
    margin-top: 0.4mm;
  }
  .wp-hdr-timestamp {
    font-size: 7.5px;
    color: #64748b;
    margin-top: 0.4mm;
  }
  .wp-hdr-badge {
    display: inline-block;
    margin-right: 5px;
    padding: 1px 5px;
    border-radius: 3px;
    border: 0.2mm solid #cbd5e1;
    background: #f1f5f9;
    color: #475569;
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    vertical-align: 1px;
  }
</style>
<div class="wp-hdr">
  <div class="wp-hdr-bar">
    <div class="wp-hdr-brand">
      {$logoTag}
      <div class="wp-hdr-divider"></div>
      <div class="wp-hdr-company">
        <span class="wp-hdr-company-name">Sécurité Services Plus</span>
        <span class="wp-hdr-company-sub">Document généré par WAVE PLUS</span>
      </div>
    </div>
    <div class="wp-hdr-meta-col">
      <div class="wp-hdr-doc-title">{$badge}{$title}</div>
      {$ref}
      <div class="wp-hdr-timestamp">Généré le {$generatedAt}</div>
    </div>
  </div>
</div>
HTML;
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function buildFooterHtml(array $meta, float $left, float $right, float $bottom): string
    {
        $zone = max($bottom - self::TEMPLATE_INSET_MM, 8);
        $company = $this->e('Sécurité Services Plus (SS Plus)');
        $details = array_filter([
            $meta['footer-address'] ?? null,
            isset($meta['footer-tax-id']) ? 'MF : '.$meta['footer-tax-id'] : null,
            isset($meta['footer-phone']) ? 'Tél : '.$meta['footer-phone'] : null,
        ]);
        $infoLine = $details !== [] ? '<div class="wp-ftr-info">'.$this->e(implode('  ·  ', $details)).'</div>' : '';
        $note = isset($meta['footer-note']) ? '<div class="wp-ftr-note">'.$this->e($meta['footer-note']).'</div>' : '';
        $year = date('Y');
        $logo = $this->logoDataUri('wave-plus');
        $logoTag = $logo !== ''
            ? '<img class="wp-ftr-logo" src="'.$logo.'" alt="WAVE PLUS">'
            : '<strong>WAVE PLUS</strong>';
        $gap = self::TEMPLATE_GAP_MM;

        return <<<HTML
<style>
  html, body { margin: 0; padding: 0; }
  * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .wp-ftr {
    width: 100%;
    height: {$zone}mm;
    padding: 0 {$right}mm 0 {$left}mm;
    font-family: "Segoe UI", Arial, Helvetica, sans-serif;
    font-size: 7.8px;
    color: #64748b;
  }
  .wp-ftr-bar {
    width: 100%;
    margin-top: {$gap}mm;
    padding-top: 2mm;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6mm;
    border-top: 0.3mm solid #cbd5e1;
  }
  .wp-ftr-col-left {
    flex: 1 1 0;
    min-width: 0;
    line-height: 1.4;
    text-align: left;
  }
  .wp-ftr-company { font-weight: 700; color: #334155; }
  .wp-ftr-info { color: #64748b; }
  .wp-ftr-note { font-style: italic; color: #64748b; }
  .wp-ftr-col-center {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.8mm;
    white-space: nowrap;
  }
  .wp-ftr-logo { height: 5.2mm; width: auto; display: block; }
  .wp-ftr-col-right {
    flex: 1 1 0;
    text-align: right;
    white-space: nowrap;
    line-height: 1.4;
  }
  .wp-ftr-page { font-size: 8.5px; font-weight: 700; color: #334155; }
</style>
<div class="wp-ftr">
  <div class="wp-ftr-bar">
    <div class="wp-ftr-col-left">
      <div class="wp-ftr-company">{$company}</div>
      {$infoLine}
      {$note}
    </div>
    <div class="wp-ftr-col-center"><span>Généré par</span>{$logoTag}</div>
    <div class="wp-ftr-col-right">
      <div class="wp-ftr-page">Page <span class="pageNumber"></span> / <span class="totalPages"></span></div>
      <div>&copy; {$year} SS Plus · Tous droits réservés</div>
    </div>
  </div>
</div>
HTML;
    }

    /**
     * Inline a brand logo as a data URI (Chrome header/footer templates cannot load relative files).
     * PNG sources are high-resolution (>1200px wide) so they stay sharp at print size.
     */
    public function logoDataUri(string $key): string
    {
        if (isset(self::$logoCache[$key])) {
            return self::$logoCache[$key];
        }

        $paths = [
            'ss-plus' => resource_path('images/logos/logo ss plus/logo ssplus.png'),
            // WAVE IO artwork is used as the WAVE PLUS brand mark.
            'wave-plus' => resource_path('images/logos/logo wave io/logo.png'),
        ];

        $path = $paths[$key] ?? null;
        $uri = '';

        if ($path !== null && is_file($path)) {
            $uri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
        }

        return self::$logoCache[$key] = $uri;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitize a filename, preserving only safe characters.
     * Ensures the correct extension is present.
     */
    private function sanitizeFilename(string $filename, string $extension): string
    {
        // Strip path separators and null bytes
        $base = basename(str_replace(["\0", '/', '\\'], '-', $filename));

        // Remove double-quotes to prevent header injection
        $base = str_replace('"', '', $base);

        // Ensure correct extension
        if (! str_ends_with(strtolower($base), '.'.$extension)) {
            $stem = pathinfo($base, PATHINFO_FILENAME);
            $base = $stem !== '' ? $stem.'.'.$extension : 'export.'.$extension;
        }

        // Final safety net — an empty or dot-only name
        $stem = pathinfo($base, PATHINFO_FILENAME);

        return ($stem !== '' && $base !== '') ? $base : 'export.'.$extension;
    }
}
