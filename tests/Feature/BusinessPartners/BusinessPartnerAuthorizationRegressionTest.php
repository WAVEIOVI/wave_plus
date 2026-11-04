<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessPartners;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpBulkEmailBatch;
use App\Modules\BusinessPartners\Models\BpDocument;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessPartnerAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Supplier $supplier;

    private ServiceProvider $serviceProvider;

    private Organisation $organisation;

    private EmailSenderProfile $senderProfile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(config('media-library.disk_name'));

        $this->client = Client::create([
            'legal_name' => 'Regression Client',
            'status' => 'active',
            'primary_email' => 'client@regression.io',
        ]);

        $this->supplier = Supplier::create([
            'legal_name' => 'Regression Supplier',
            'status' => 'active',
            'primary_email' => 'supplier@regression.io',
        ]);

        $this->serviceProvider = ServiceProvider::create([
            'legal_name' => 'Regression SP',
            'status' => 'active',
            'primary_email' => 'sp@regression.io',
        ]);

        $this->organisation = Organisation::create([
            'legal_name' => 'Regression Org',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
            'primary_email' => 'org@regression.io',
        ]);

        $this->senderProfile = EmailSenderProfile::create([
            'name' => 'General Sender',
            'email' => 'sender@regression.io',
            'department' => 'sales',
            'is_system' => false,
        ]);
    }

    private function createDocumentFor(mixed $partner): BpDocument
    {
        $file = UploadedFile::fake()->createWithContent('test_doc.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $doc = BpDocument::create([
            'documentable_type' => $partner::class,
            'documentable_id' => $partner->id,
            'title' => 'Sample Doc',
            'type' => 'other',
        ]);

        $doc->addMedia($file)->toMediaCollection('document');

        return $doc->fresh();
    }

    public function test_super_admin_has_unrestricted_universal_access(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        Sanctum::actingAs($superAdmin);

        // Dashboard
        $dashboard = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $dashboard);
        $this->assertArrayHasKey('suppliers', $dashboard);
        $this->assertArrayHasKey('service_providers', $dashboard);
        $this->assertArrayHasKey('organisations', $dashboard);

        // Read all 4 entities
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk();
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertOk();
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertOk();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertOk();

        // Exports
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertOk();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertOk();
        $this->getJson('/api/bp/service-providers/export/pdf')->assertOk();
        $this->getJson('/api/bp/organisations/export/pdf')->assertOk();

        // Bank Accounts (full management)
        $bank = $this->postJson("/api/bp/clients/{$this->client->id}/bank-accounts", [
            'bank_name' => 'Super Admin Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertCreated();
        $bankId = $bank->json('data.id');
        $this->getJson("/api/bp/bank-accounts/{$bankId}")->assertOk();
        $this->deleteJson("/api/bp/bank-accounts/{$bankId}")->assertOk();

        // Documents
        $doc = $this->createDocumentFor($this->client);
        $this->getJson("/api/bp/documents/{$doc->id}")->assertOk();
        $this->getJson("/api/bp/documents/{$doc->id}/download")->assertOk();

        // Bulk Email Batches created by anyone
        $otherUser = User::factory()->create();
        $batch = BpBulkEmailBatch::create([
            'sender_profile_id' => $this->senderProfile->id,
            'partner_type' => 'client',
            'status' => 'completed',
            'created_by' => $otherUser->id,
            'total_targets' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'skipped_count' => 0,
        ]);
        $this->getJson("/api/bp/bulk-email/batches/{$batch->id}")->assertOk();
    }

    public function test_admin_has_full_permission_access(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);

        // Dashboard
        $dashboard = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $dashboard);
        $this->assertArrayHasKey('suppliers', $dashboard);
        $this->assertArrayHasKey('service_providers', $dashboard);
        $this->assertArrayHasKey('organisations', $dashboard);

        // CRUD & Export
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertOk();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertOk();

        // Bank accounts
        $bank = $this->postJson("/api/bp/clients/{$this->client->id}/bank-accounts", [
            'bank_name' => 'Admin Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertCreated();
        $this->getJson("/api/bp/bank-accounts/{$bank->json('data.id')}")->assertOk();

        // Documents
        $doc = $this->createDocumentFor($this->supplier);
        $this->getJson("/api/bp/documents/{$doc->id}")->assertOk();
    }

    public function test_bp_manager_role_audit(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('BP Manager');
        Sanctum::actingAs($manager);

        // Dashboard
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('service_providers', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);

        // All partner types readable & updatable
        $this->putJson("/api/bp/clients/{$this->client->id}", ['legal_name' => 'Manager Client'])->assertOk();
        $this->putJson("/api/bp/suppliers/{$this->supplier->id}", ['legal_name' => 'Manager Supplier'])->assertOk();
        $this->putJson("/api/bp/service-providers/{$this->serviceProvider->id}", ['legal_name' => 'Manager SP'])->assertOk();
        $this->putJson("/api/bp/organisations/{$this->organisation->id}", ['legal_name' => 'Manager Org'])->assertOk();

        // Exports for all 4 types
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertOk();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertOk();
        $this->getJson('/api/bp/service-providers/export/pdf')->assertOk();
        $this->getJson('/api/bp/organisations/export/pdf')->assertOk();

        // Bank Accounts (full management)
        $bank = $this->postJson("/api/bp/clients/{$this->client->id}/bank-accounts", [
            'bank_name' => 'BP Manager Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertCreated();
        $bankId = $bank->json('data.id');
        $this->getJson("/api/bp/bank-accounts/{$bankId}")->assertOk();
        $this->putJson("/api/bp/bank-accounts/{$bankId}", ['bank_name' => 'Updated Bank'])->assertOk();
        $this->deleteJson("/api/bp/bank-accounts/{$bankId}")->assertOk();

        // Documents (upload & view)
        $doc = $this->createDocumentFor($this->serviceProvider);
        $this->getJson("/api/bp/documents/{$doc->id}")->assertOk();
        $this->getJson("/api/bp/documents/{$doc->id}/download")->assertOk();

        // Bulk Email Batches (can view any user's batch)
        $otherUser = User::factory()->create();
        $batch = BpBulkEmailBatch::create([
            'sender_profile_id' => $this->senderProfile->id,
            'partner_type' => 'supplier',
            'status' => 'completed',
            'created_by' => $otherUser->id,
            'total_targets' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'skipped_count' => 0,
        ]);
        $this->getJson("/api/bp/bulk-email/batches/{$batch->id}")->assertOk();
    }

    public function test_sales_responsible_role_audit(): void
    {
        $salesUser = User::factory()->create();
        $salesUser->assignRole('Sales Responsible');
        Sanctum::actingAs($salesUser);

        // Dashboard: clients and organisations only
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);
        $this->assertArrayNotHasKey('suppliers', $kpis);
        $this->assertArrayNotHasKey('service_providers', $kpis);

        // Clients: CRUD, Export, Email, Documents
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk();
        $this->putJson("/api/bp/clients/{$this->client->id}", ['legal_name' => 'Sales Updated'])->assertOk();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertOk();
        $clientDoc = $this->createDocumentFor($this->client);
        $this->getJson("/api/bp/documents/{$clientDoc->id}")->assertOk();

        // Organisations: Read only, Documents read only
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertOk();
        $orgDoc = $this->createDocumentFor($this->organisation);
        $this->getJson("/api/bp/documents/{$orgDoc->id}")->assertOk();

        // FORBIDDEN on Organisations: Write / Delete / Export
        $this->postJson('/api/bp/organisations', [
            'legal_name' => 'Forbidden Org',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ])->assertForbidden();
        $this->putJson("/api/bp/organisations/{$this->organisation->id}", ['legal_name' => 'Forbidden Edit'])->assertForbidden();
        $this->deleteJson("/api/bp/organisations/{$this->organisation->id}")->assertForbidden();
        $this->getJson('/api/bp/organisations/export/pdf')->assertForbidden();

        // FORBIDDEN on Suppliers: All operations
        $this->getJson('/api/bp/suppliers')->assertForbidden();
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertForbidden();
        $this->postJson('/api/bp/suppliers', ['legal_name' => 'Forbidden Supplier'])->assertForbidden();
        $this->putJson("/api/bp/suppliers/{$this->supplier->id}", ['legal_name' => 'Forbidden'])->assertForbidden();
        $this->deleteJson("/api/bp/suppliers/{$this->supplier->id}")->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();
        $supplierDoc = $this->createDocumentFor($this->supplier);
        $this->getJson("/api/bp/documents/{$supplierDoc->id}")->assertForbidden();

        // FORBIDDEN on Service Providers: All operations
        $this->getJson('/api/bp/service-providers')->assertForbidden();
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();

        // FORBIDDEN: Bank accounts management
        $this->postJson("/api/bp/clients/{$this->client->id}/bank-accounts", [
            'bank_name' => 'Forbidden Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();
    }

    public function test_procurement_responsible_role_audit(): void
    {
        $procUser = User::factory()->create();
        $procUser->assignRole('Procurement Responsible');
        Sanctum::actingAs($procUser);

        // Dashboard: suppliers and service providers only
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('service_providers', $kpis);
        $this->assertArrayNotHasKey('clients', $kpis);
        $this->assertArrayNotHasKey('organisations', $kpis);

        // Suppliers: CRUD, Export, Documents
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertOk();
        $this->putJson("/api/bp/suppliers/{$this->supplier->id}", ['legal_name' => 'Procurement Updated'])->assertOk();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertOk();
        $supplierDoc = $this->createDocumentFor($this->supplier);
        $this->getJson("/api/bp/documents/{$supplierDoc->id}")->assertOk();

        // Service Providers: Read only
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertOk();
        $spDoc = $this->createDocumentFor($this->serviceProvider);
        $this->getJson("/api/bp/documents/{$spDoc->id}")->assertOk();

        // FORBIDDEN on Service Providers: Write / Delete / Export
        $this->postJson('/api/bp/service-providers', ['legal_name' => 'Forbidden SP'])->assertForbidden();
        $this->putJson("/api/bp/service-providers/{$this->serviceProvider->id}", ['legal_name' => 'Forbidden Edit'])->assertForbidden();
        $this->deleteJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();
        $this->getJson('/api/bp/service-providers/export/pdf')->assertForbidden();

        // FORBIDDEN on Clients: All operations
        $this->getJson('/api/bp/clients')->assertForbidden();
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertForbidden();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertForbidden();
        $clientDoc = $this->createDocumentFor($this->client);
        $this->getJson("/api/bp/documents/{$clientDoc->id}")->assertForbidden();

        // FORBIDDEN on Organisations: All operations
        $this->getJson('/api/bp/organisations')->assertForbidden();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertForbidden();

        // FORBIDDEN: Bank accounts management
        $this->postJson("/api/bp/suppliers/{$this->supplier->id}/bank-accounts", [
            'bank_name' => 'Forbidden Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();
    }

    public function test_financial_responsible_role_audit(): void
    {
        $finUser = User::factory()->create();
        $finUser->assignRole('Financial Responsible');
        Sanctum::actingAs($finUser);

        $bankAccount = $this->client->bankAccounts()->create([
            'bank_name' => 'Financial Bank',
            'iban' => 'FR7630006000011234567890189',
            'is_primary' => true,
        ]);

        // Dashboard: clients, suppliers, organisations only
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);
        $this->assertArrayNotHasKey('service_providers', $kpis);

        // Read clients, suppliers, organisations
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk();
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertOk();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertOk();

        // Read bank accounts (both direct and embedded in resource)
        $this->getJson("/api/bp/bank-accounts/{$bankAccount->id}")->assertOk();
        $clientRes = $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk()->json('data');
        $this->assertNotEmpty($clientRes['bank_accounts']);
        $this->assertSame('Financial Bank', $clientRes['bank_accounts'][0]['bank_name']);

        // FORBIDDEN: Partner writes/deletes
        $this->postJson('/api/bp/clients', ['legal_name' => 'New Client'])->assertForbidden();
        $this->putJson("/api/bp/clients/{$this->client->id}", ['legal_name' => 'Edit Client'])->assertForbidden();
        $this->deleteJson("/api/bp/clients/{$this->client->id}")->assertForbidden();

        // FORBIDDEN: Exports
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();

        // FORBIDDEN: Bank accounts management (create/update/delete)
        $this->postJson("/api/bp/clients/{$this->client->id}/bank-accounts", [
            'bank_name' => 'Unauthorized Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();
        $this->putJson("/api/bp/bank-accounts/{$bankAccount->id}", ['bank_name' => 'New Name'])->assertForbidden();
        $this->deleteJson("/api/bp/bank-accounts/{$bankAccount->id}")->assertForbidden();

        // FORBIDDEN: Service providers
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();

        // FORBIDDEN: Email sending
        $this->postJson("/api/bp/clients/{$this->client->id}/send-email", [
            'to' => [['email' => 'test@regression.io']],
            'subject' => 'Financial notice',
            'body_html' => '<p>Notice</p>',
        ])->assertForbidden();
    }

    public function test_bp_viewer_role_audit(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('BP Viewer');
        Sanctum::actingAs($viewer);

        $bankAccount = $this->client->bankAccounts()->create([
            'bank_name' => 'Hidden Bank',
            'iban' => 'FR7630006000011234567890189',
            'is_primary' => true,
        ]);

        // Dashboard: all 4 types
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('service_providers', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);

        // Read all 4 partner types
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk();
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertOk();
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertOk();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertOk();

        // Bank accounts MUST BE HIDDEN (both direct endpoint and embedded in resource)
        $this->getJson("/api/bp/bank-accounts/{$bankAccount->id}")->assertForbidden();
        $clientRes = $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk()->json('data');
        $this->assertEmpty($clientRes['bank_accounts']);

        // FORBIDDEN: Any create, edit, delete, export, or email
        $this->postJson('/api/bp/clients', ['legal_name' => 'Viewer Client'])->assertForbidden();
        $this->putJson("/api/bp/clients/{$this->client->id}", ['legal_name' => 'Edit'])->assertForbidden();
        $this->deleteJson("/api/bp/clients/{$this->client->id}")->assertForbidden();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();
        $this->postJson("/api/bp/clients/{$this->client->id}/send-email", [
            'to' => [['email' => 'viewer@regression.io']],
            'subject' => 'Viewer Email',
            'body_html' => '<p>Hello</p>',
        ])->assertForbidden();
    }

    public function test_users_with_individual_permissions(): void
    {
        // 1. User with ONLY read-clients
        $clientReader = User::factory()->create();
        $clientReader->givePermissionTo('read-clients');
        Sanctum::actingAs($clientReader);

        $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk();
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertForbidden();
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertForbidden();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertForbidden();
        $this->getJson('/api/bp/dashboard-stats')->assertForbidden(); // Needs read-bp-dashboard

        // 2. User with ONLY export-suppliers
        $supplierExporter = User::factory()->create();
        $supplierExporter->givePermissionTo('export-suppliers');
        Sanctum::actingAs($supplierExporter);

        $this->getJson('/api/bp/suppliers/export/pdf')->assertOk();
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertForbidden();
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertForbidden();

        // 3. User with ONLY read-BusinessPartners
        $bpNavOnly = User::factory()->create();
        $bpNavOnly->givePermissionTo('read-BusinessPartners');
        Sanctum::actingAs($bpNavOnly);

        $this->getJson("/api/bp/clients/{$this->client->id}")->assertForbidden();
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertForbidden();
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertForbidden();
        $this->getJson('/api/bp/dashboard-stats')->assertForbidden();

        // 4. User with manage-bp-bank-accounts BUT missing edit-clients
        $bankManagerOnly = User::factory()->create();
        $bankManagerOnly->givePermissionTo(['read-clients', 'manage-bp-bank-accounts']);
        Sanctum::actingAs($bankManagerOnly);

        // Cannot create bank account because policy enforces update access on the client owner
        $this->postJson("/api/bp/clients/{$this->client->id}/bank-accounts", [
            'bank_name' => 'Failing Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();
    }

    public function test_users_with_multiple_roles(): void
    {
        // User with Sales Responsible + Financial Responsible
        $multiUser = User::factory()->create();
        $multiUser->assignRole(['Sales Responsible', 'Financial Responsible']);
        Sanctum::actingAs($multiUser);

        // Can CRUD clients (from Sales)
        $this->putJson("/api/bp/clients/{$this->client->id}", ['legal_name' => 'Multi Updated'])->assertOk();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertOk();

        // Can read suppliers and organisations (from Financial)
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertOk();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertOk();

        // Can read bank accounts (from Financial)
        $bank = $this->client->bankAccounts()->create([
            'bank_name' => 'Shared Bank',
            'iban' => 'FR7630006000011234567890189',
            'is_primary' => true,
        ]);
        $this->getJson("/api/bp/bank-accounts/{$bank->id}")->assertOk();

        // Still FORBIDDEN from supplier CRUD/export and bank account management
        $this->postJson('/api/bp/suppliers', ['legal_name' => 'Forbidden Supplier'])->assertForbidden();
        $this->deleteJson("/api/bp/suppliers/{$this->supplier->id}")->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();
        $this->postJson("/api/bp/clients/{$this->client->id}/bank-accounts", [
            'bank_name' => 'Forbidden',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();

        // Still FORBIDDEN from service providers
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();
    }

    public function test_users_with_no_bp_permissions_are_completely_blocked(): void
    {
        // 1. Unauthenticated user
        $this->getJson('/api/bp/clients')->assertUnauthorized();
        $this->getJson('/api/bp/dashboard-stats')->assertUnauthorized();

        // 2. Authenticated user with unrelated permissions (e.g. read-users)
        $unrelatedUser = User::factory()->create();
        $unrelatedUser->givePermissionTo('read-users');
        Sanctum::actingAs($unrelatedUser);

        $this->getJson('/api/bp/clients')->assertForbidden();
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertForbidden();
        $this->getJson('/api/bp/suppliers')->assertForbidden();
        $this->getJson('/api/bp/service-providers')->assertForbidden();
        $this->getJson('/api/bp/organisations')->assertForbidden();
        $this->getJson('/api/bp/dashboard-stats')->assertForbidden();
        $this->getJson('/api/bp/bulk-email/batches')->assertForbidden();
    }

    public function test_cross_partner_isolation_audit(): void
    {
        // User with complete client authority (CRUD + export)
        $clientChampion = User::factory()->create();
        $clientChampion->givePermissionTo([
            'read-BusinessPartners',
            'read-clients',
            'create-clients',
            'edit-clients',
            'delete-clients',
            'export-clients',
        ]);
        Sanctum::actingAs($clientChampion);

        // Has full authority over clients
        $this->getJson("/api/bp/clients/{$this->client->id}")->assertOk();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$this->client->id]])->assertOk();

        // Must be completely blocked from all other partner types
        // Suppliers
        $this->getJson('/api/bp/suppliers')->assertForbidden();
        $this->getJson("/api/bp/suppliers/{$this->supplier->id}")->assertForbidden();
        $this->postJson('/api/bp/suppliers', ['legal_name' => 'Sneak Supplier'])->assertForbidden();
        $this->putJson("/api/bp/suppliers/{$this->supplier->id}", ['legal_name' => 'Sneak Edit'])->assertForbidden();
        $this->deleteJson("/api/bp/suppliers/{$this->supplier->id}")->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();

        // Service Providers
        $this->getJson('/api/bp/service-providers')->assertForbidden();
        $this->getJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();
        $this->postJson('/api/bp/service-providers', ['legal_name' => 'Sneak SP'])->assertForbidden();
        $this->putJson("/api/bp/service-providers/{$this->serviceProvider->id}", ['legal_name' => 'Sneak Edit'])->assertForbidden();
        $this->deleteJson("/api/bp/service-providers/{$this->serviceProvider->id}")->assertForbidden();
        $this->getJson('/api/bp/service-providers/export/pdf')->assertForbidden();

        // Organisations
        $this->getJson('/api/bp/organisations')->assertForbidden();
        $this->getJson("/api/bp/organisations/{$this->organisation->id}")->assertForbidden();
        $this->postJson('/api/bp/organisations', [
            'legal_name' => 'Sneak Org',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ])->assertForbidden();
        $this->putJson("/api/bp/organisations/{$this->organisation->id}", ['legal_name' => 'Sneak Edit'])->assertForbidden();
        $this->deleteJson("/api/bp/organisations/{$this->organisation->id}")->assertForbidden();
        $this->getJson('/api/bp/organisations/export/pdf')->assertForbidden();
    }
}
