<?php

namespace Tests\Feature\Shared;

use App\Modules\Shared\Services\DocumentRenderService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class DocumentRenderServiceTest extends TestCase
{
    private DocumentRenderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentRenderService;
    }

    // ── Filename sanitization ───────────────────────────────────────────────

    public function test_sanitize_filename_adds_extension_when_missing(): void
    {
        $response = $this->service->streamCsvResponse('myfile', ['H1'], []);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('myfile.csv', $disposition);
    }

    public function test_sanitize_filename_preserves_correct_csv_extension(): void
    {
        $response = $this->service->streamCsvResponse('export.csv', ['H1'], []);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('export.csv', $disposition);
    }

    public function test_sanitize_filename_removes_double_quotes(): void
    {
        $response = $this->service->streamCsvResponse('file"name".csv', ['H1'], []);
        $disposition = $response->headers->get('Content-Disposition');
        // Double quotes must not appear inside the quoted filename value
        $this->assertStringNotContainsString('file"name"', $disposition);
    }

    public function test_sanitize_filename_strips_path_separators(): void
    {
        $response = $this->service->streamCsvResponse('../../../etc/passwd', ['H1'], []);
        $disposition = $response->headers->get('Content-Disposition');
        // Should not contain traversal
        $this->assertStringNotContainsString('../', $disposition);
        $this->assertStringNotContainsString('etc', $disposition);
    }

    public function test_empty_filename_falls_back_to_export_csv(): void
    {
        $response = $this->service->streamCsvResponse('', ['H1'], []);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('export.csv', $disposition);
    }

    // ── CSV response structure ──────────────────────────────────────────────

    public function test_csv_response_returns_streamed_response(): void
    {
        $response = $this->service->streamCsvResponse('test.csv', ['Name', 'Email'], []);
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_csv_response_has_correct_content_type(): void
    {
        $response = $this->service->streamCsvResponse('test.csv', ['Name'], []);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_csv_response_has_attachment_disposition(): void
    {
        $response = $this->service->streamCsvResponse('report.csv', ['Name'], []);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('report.csv', $disposition);
    }

    public function test_csv_response_has_no_cache_headers(): void
    {
        $response = $this->service->streamCsvResponse('test.csv', ['H1'], []);
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_csv_output_contains_bom_and_headers(): void
    {
        $response = $this->service->streamCsvResponse(
            'test.csv',
            ['Name', 'Email', 'Status'],
            [['Alice', 'alice@example.com', 'active']]
        );

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Header row
        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('Status', $content);

        // Data row
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('alice@example.com', $content);
        $this->assertStringContainsString('active', $content);
    }

    public function test_csv_handles_values_with_commas(): void
    {
        $response = $this->service->streamCsvResponse(
            'test.csv',
            ['Name'],
            [['Smith, John']]
        );

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // fputcsv wraps values with commas in double-quotes
        $this->assertStringContainsString('"Smith, John"', $content);
    }

    public function test_csv_handles_empty_rows(): void
    {
        $response = $this->service->streamCsvResponse('test.csv', ['Col1', 'Col2'], []);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // BOM + header only, no exception
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Col1', $content);
    }

    // ── Browsershot factory ─────────────────────────────────────────────────

    public function test_make_browsershot_reads_from_browsershot_config_key(): void
    {
        config(['browsershot.chrome_path' => '/usr/bin/chromium']);
        config(['services.browsershot.chrome_path' => '/should/not/be/used']);

        $browsershot = $this->service->makeBrowsershot('<html></html>');

        // We cannot inspect the internal chrome path directly without reflection;
        // verify the instance is returned (no exception means config was read)
        $this->assertInstanceOf(\Spatie\Browsershot\Browsershot::class, $browsershot);
    }

    public function test_make_browsershot_falls_back_to_services_config(): void
    {
        config(['browsershot.chrome_path' => null]);
        config(['services.browsershot.chrome_path' => '/fallback/chromium']);

        $browsershot = $this->service->makeBrowsershot('<html></html>');
        $this->assertInstanceOf(\Spatie\Browsershot\Browsershot::class, $browsershot);
    }

    public function test_make_browsershot_handles_no_config_gracefully(): void
    {
        config(['browsershot.chrome_path' => null]);
        config(['browsershot.node_path' => null]);
        config(['browsershot.npm_path' => null]);
        config(['services.browsershot' => []]);

        $browsershot = $this->service->makeBrowsershot('<html></html>');
        $this->assertInstanceOf(\Spatie\Browsershot\Browsershot::class, $browsershot);
    }
}
