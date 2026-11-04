<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessPartners;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Shared\Services\DocumentRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

/**
 * Tests for ClientPdfController and ClientCsvController export endpoints.
 *
 * PDF tests mock DocumentRenderService->makeBrowsershot() because Browsershot
 * requires a running Chrome binary which is unavailable in the test environment.
 * CSV tests run against the real service (pure PHP, no external dependency).
 */
class ClientExportTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ────────────────────────────────────────────────────────────

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    private function actingWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Mock DocumentRenderService::makeBrowsershot so PDF generation can be tested
     * without a headless Chrome binary while still exercising renderPdfResponse()
     * and capturing the exact Blade HTML passed to Browsershot.
     */
    private function mockBrowsershot(?string &$capturedHtml = null): void
    {
        $serviceMock = Mockery::mock(DocumentRenderService::class)->makePartial();
        $serviceMock->shouldReceive('makeBrowsershot')
            ->andReturnUsing(function (string $html) use (&$capturedHtml) {
                $capturedHtml = $html;

                $browsershotMock = Mockery::mock(Browsershot::class);
                $browsershotMock->shouldReceive('margins')->andReturnSelf();
                $browsershotMock->shouldReceive('landscape')->andReturnSelf();
                $browsershotMock->shouldReceive('pdf')->andReturn('%PDF-1.4 fake-pdf-bytes');

                return $browsershotMock;
            });

        $this->app->instance(DocumentRenderService::class, $serviceMock);
    }

    // ── CSV Export — Authorization ──────────────────────────────────────────

    public function test_unauthenticated_user_cannot_export_clients_csv(): void
    {
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [1]])->assertUnauthorized();
    }

    public function test_user_without_bp_access_cannot_export_clients_csv(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/bp/clients/export/csv', ['ids' => [1]])->assertForbidden();
    }

    public function test_user_without_read_permission_cannot_export_csv(): void
    {
        $client = Client::create(['legal_name' => 'NoRead Corp', 'status' => 'active']);
        $this->actingWithPermissions(['create-clients']);

        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$client->id]])->assertForbidden();
    }

    public function test_user_with_read_clients_permission_cannot_export_csv_without_export_permission(): void
    {
        $client = Client::create(['legal_name' => 'ReadClient SARL', 'status' => 'active']);
        $this->actingWithPermissions(['read-clients']);

        $response = $this->post('/api/bp/clients/export/csv', ['ids' => [$client->id]]);
        $response->assertForbidden();
    }

    public function test_user_with_read_business_partners_permission_alone_cannot_export_csv(): void
    {
        $client = Client::create(['legal_name' => 'ReadBp SARL', 'status' => 'active']);
        $this->actingWithPermissions(['read-BusinessPartners']);

        $response = $this->post('/api/bp/clients/export/csv', ['ids' => [$client->id]]);
        $response->assertForbidden();
    }

    public function test_user_with_export_clients_permission_can_export_csv(): void
    {
        $client = Client::create(['legal_name' => 'ExportClient SARL', 'status' => 'active']);
        $this->actingWithPermissions(['export-clients']);

        $response = $this->post('/api/bp/clients/export/csv', ['ids' => [$client->id]]);
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_super_admin_can_export_clients_csv(): void
    {
        $client = Client::create(['legal_name' => 'AdminExport SARL', 'status' => 'active']);
        $this->actingAsSuperAdmin();

        $response = $this->post('/api/bp/clients/export/csv', ['ids' => [$client->id]]);
        $response->assertOk();
    }

    // ── Validation: Reject Empty, Missing, Invalid, or Inaccessible Selection ──

    public function test_csv_export_rejects_missing_or_empty_selection_and_never_falls_back_to_all(): void
    {
        $this->actingAsSuperAdmin();
        Client::create(['legal_name' => 'Existing Client', 'status' => 'active']);

        // Missing ids
        $this->getJson('/api/bp/clients/export/csv')->assertStatus(422)->assertJsonValidationErrors(['ids']);
        $this->postJson('/api/bp/clients/export/csv', [])->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // Empty ids array
        $this->postJson('/api/bp/clients/export/csv', ['ids' => []])->assertStatus(422)->assertJsonValidationErrors(['ids']);
    }

    public function test_pdf_export_rejects_missing_or_empty_selection_and_never_falls_back_to_all(): void
    {
        $this->actingAsSuperAdmin();
        Client::create(['legal_name' => 'Existing Client', 'status' => 'active']);

        // Missing ids
        $this->getJson('/api/bp/clients/export/pdf')->assertStatus(422)->assertJsonValidationErrors(['ids']);
        $this->postJson('/api/bp/clients/export/pdf', [])->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // Empty ids array
        $this->postJson('/api/bp/clients/export/pdf', ['ids' => []])->assertStatus(422)->assertJsonValidationErrors(['ids']);
    }

    public function test_export_rejects_invalid_non_existent_and_soft_deleted_ids(): void
    {
        $this->actingAsSuperAdmin();

        $activeClient = Client::create(['legal_name' => 'Active Client', 'status' => 'active']);
        $deletedClient = Client::create(['legal_name' => 'Soft Deleted Client', 'status' => 'inactive']);
        $deletedClient->delete();

        // Non-existent ULID
        $nonExistentUlid = (string) \Illuminate\Support\Str::ulid();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$nonExistentUlid]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0']);

        $this->postJson('/api/bp/clients/export/pdf', ['ids' => [$nonExistentUlid]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0']);

        // Soft-deleted (inaccessible) ID
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$activeClient->id, $deletedClient->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.1']);

        $this->postJson('/api/bp/clients/export/pdf', ['ids' => [$activeClient->id, $deletedClient->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.1']);

        // Malformed non-ULID ID
        $this->postJson('/api/bp/clients/export/csv', ['ids' => ['not-a-valid-ulid']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0']);
    }

    // ── CSV Export — Response Shape & Selected Dataset ─────────────────────

    public function test_csv_export_returns_correct_content_type_filename_bom_and_headers(): void
    {
        $this->actingAsSuperAdmin();
        $client = Client::create(['legal_name' => 'Header Check SARL', 'status' => 'active']);

        $response = $this->post('/api/bp/clients/export/csv', ['ids' => [$client->id]]);
        $response->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertMatchesRegularExpression('/clients-\d{4}-\d{2}-\d{2}\.csv/', $disposition);

        $rawContent = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $rawContent);

        $content = ltrim($rawContent, "\xEF\xBB\xBF");
        $this->assertStringContainsString('ID Client', $content);
        $this->assertStringContainsString('Raison Sociale', $content);
        $this->assertStringContainsString('Statut', $content);
        $this->assertStringContainsString('Email', $content);
    }

    public function test_csv_export_with_one_selected_record_excludes_unselected_records(): void
    {
        $this->actingAsSuperAdmin();

        $selected = Client::create([
            'legal_name' => 'Alpha Selected SARL',
            'primary_email' => 'alpha@selected.tn',
            'status' => 'active',
        ]);
        $unselectedA = Client::create([
            'legal_name' => 'Beta Unselected SARL',
            'primary_email' => 'beta@unselected.tn',
            'status' => 'active',
        ]);
        $unselectedB = Client::create([
            'legal_name' => 'Gamma Unselected SARL',
            'primary_email' => 'gamma@unselected.tn',
            'status' => 'prospect',
        ]);

        $response = $this->post('/api/bp/clients/export/csv', ['ids' => [$selected->id]]);
        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString($selected->client_id, $content);
        $this->assertStringContainsString('Alpha Selected SARL', $content);
        $this->assertStringContainsString('alpha@selected.tn', $content);

        $this->assertStringNotContainsString($unselectedA->client_id, $content);
        $this->assertStringNotContainsString('Beta Unselected SARL', $content);
        $this->assertStringNotContainsString('beta@unselected.tn', $content);

        $this->assertStringNotContainsString($unselectedB->client_id, $content);
        $this->assertStringNotContainsString('Gamma Unselected SARL', $content);
    }

    public function test_csv_export_with_multiple_selected_records_includes_only_selected(): void
    {
        $this->actingAsSuperAdmin();

        $c1 = Client::create(['legal_name' => 'Client One Corp', 'primary_email' => 'one@corp.tn', 'status' => 'active']);
        $c2 = Client::create(['legal_name' => 'Client Two Corp', 'primary_email' => 'two@corp.tn', 'status' => 'active']);
        $c3 = Client::create(['legal_name' => 'Client Three Corp', 'primary_email' => 'three@corp.tn', 'status' => 'inactive']);
        $c4 = Client::create(['legal_name' => 'Client Four Corp', 'primary_email' => 'four@corp.tn', 'status' => 'prospect']);

        $response = $this->post('/api/bp/clients/export/csv', ['ids' => [$c1->id, $c3->id]]);
        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString($c1->client_id, $content);
        $this->assertStringContainsString('Client One Corp', $content);
        $this->assertStringContainsString($c3->client_id, $content);
        $this->assertStringContainsString('Client Three Corp', $content);

        $this->assertStringNotContainsString($c2->client_id, $content);
        $this->assertStringNotContainsString('Client Two Corp', $content);
        $this->assertStringNotContainsString($c4->client_id, $content);
        $this->assertStringNotContainsString('Client Four Corp', $content);
    }

    // ── PDF Export — Authorization & Selected Dataset (mocked Browsershot) ──

    public function test_unauthenticated_user_cannot_export_clients_pdf(): void
    {
        $this->postJson('/api/bp/clients/export/pdf', ['ids' => [1]])->assertUnauthorized();
    }

    public function test_user_without_bp_access_cannot_export_clients_pdf(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/bp/clients/export/pdf', ['ids' => [1]])->assertForbidden();
    }

    public function test_user_without_export_permission_cannot_export_pdf(): void
    {
        $client = Client::create(['legal_name' => 'NoRead PDF Corp', 'status' => 'active']);
        $this->actingWithPermissions(['read-clients']);

        $this->postJson('/api/bp/clients/export/pdf', ['ids' => [$client->id]])->assertForbidden();
    }

    public function test_pdf_export_with_one_selected_record_excludes_unselected_records(): void
    {
        $this->actingAsSuperAdmin();

        $selected = Client::create([
            'legal_name' => 'PdfSelected Alpha',
            'primary_email' => 'alpha@pdf.tn',
            'status' => 'active',
        ]);
        $unselected = Client::create([
            'legal_name' => 'PdfUnselected Beta',
            'primary_email' => 'beta@pdf.tn',
            'status' => 'active',
        ]);

        $capturedHtml = null;
        $this->mockBrowsershot($capturedHtml);

        $response = $this->postJson('/api/bp/clients/export/pdf', ['ids' => [$selected->id]]);
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('clients-list.pdf', (string) $response->headers->get('Content-Disposition'));

        $this->assertNotNull($capturedHtml);
        $this->assertStringContainsString($selected->client_id, $capturedHtml);
        $this->assertStringContainsString('PdfSelected Alpha', $capturedHtml);
        $this->assertStringNotContainsString($unselected->client_id, $capturedHtml);
        $this->assertStringNotContainsString('PdfUnselected Beta', $capturedHtml);
    }

    public function test_pdf_export_with_multiple_selected_records_includes_only_selected(): void
    {
        $this->actingWithPermissions(['export-clients']);

        $c1 = Client::create(['legal_name' => 'PdfMulti One', 'status' => 'active']);
        $c2 = Client::create(['legal_name' => 'PdfMulti Two', 'status' => 'active']);
        $c3 = Client::create(['legal_name' => 'PdfMulti Three', 'status' => 'prospect']);

        $capturedHtml = null;
        $this->mockBrowsershot($capturedHtml);

        $response = $this->postJson('/api/bp/clients/export/pdf', ['ids' => [$c1->id, $c3->id]]);
        $response->assertOk();

        $this->assertNotNull($capturedHtml);
        $this->assertStringContainsString($c1->client_id, $capturedHtml);
        $this->assertStringContainsString('PdfMulti One', $capturedHtml);
        $this->assertStringContainsString($c3->client_id, $capturedHtml);
        $this->assertStringContainsString('PdfMulti Three', $capturedHtml);

        $this->assertStringNotContainsString($c2->client_id, $capturedHtml);
        $this->assertStringNotContainsString('PdfMulti Two', $capturedHtml);
    }

    // ── PDF single export — Authorization ──────────────────────────────────

    public function test_unauthenticated_user_cannot_export_single_client_pdf(): void
    {
        $client = Client::create(['legal_name' => 'Test Client', 'status' => 'active']);
        $this->getJson('/api/bp/clients/'.$client->id.'/export/pdf')->assertUnauthorized();
    }

    public function test_user_without_export_permission_cannot_export_single_client_pdf(): void
    {
        $this->actingWithPermissions(['read-clients']);

        $client = Client::create(['legal_name' => 'Test Client', 'status' => 'active']);
        $this->getJson('/api/bp/clients/'.$client->id.'/export/pdf')->assertForbidden();
    }

    public function test_user_with_export_permission_can_export_single_client_pdf(): void
    {
        $this->actingWithPermissions(['export-clients']);

        $client = Client::create(['legal_name' => 'Test Client', 'status' => 'active']);

        $capturedHtml = null;
        $this->mockBrowsershot($capturedHtml);

        $this->getJson('/api/bp/clients/'.$client->id.'/export/pdf')->assertOk();
    }
}
