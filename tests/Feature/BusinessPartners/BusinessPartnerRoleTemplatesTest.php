<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessPartners;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\BpBulkEmailBatch;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessPartnerRoleTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_role_templates_exist_and_have_exact_permissions(): void
    {
        $expectedTemplates = [
            'BP Manager' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'create-clients',
                'edit-clients',
                'delete-clients',
                'export-clients',
                'read-suppliers',
                'create-suppliers',
                'edit-suppliers',
                'delete-suppliers',
                'export-suppliers',
                'read-service-providers',
                'create-service-providers',
                'edit-service-providers',
                'delete-service-providers',
                'export-service-providers',
                'read-organisations',
                'create-organisations',
                'edit-organisations',
                'delete-organisations',
                'export-organisations',
                'read-bp-bank-accounts',
                'manage-bp-bank-accounts',
                'create-email-messages',
                'view-all-bp-email-batches',
            ],
            'Sales Responsible' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'create-clients',
                'edit-clients',
                'delete-clients',
                'export-clients',
                'read-organisations',
                'create-email-messages',
            ],
            'Procurement Responsible' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-suppliers',
                'create-suppliers',
                'edit-suppliers',
                'delete-suppliers',
                'export-suppliers',
                'read-service-providers',
                'create-email-messages',
            ],
            'Financial Responsible' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'read-suppliers',
                'read-organisations',
                'read-bp-bank-accounts',
            ],
            'BP Viewer' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'read-suppliers',
                'read-service-providers',
                'read-organisations',
            ],
        ];

        foreach ($expectedTemplates as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $this->assertNotNull($role, "Role [{$roleName}] was not found.");

            $rolePermissions = $role->permissions->pluck('name')->all();
            sort($rolePermissions);
            $expectedSorted = $permissions;
            sort($expectedSorted);

            $this->assertEquals($expectedSorted, $rolePermissions, "Permissions for role [{$roleName}] do not match expected template.");
        }
    }

    public function test_seeding_is_idempotent_and_does_not_duplicate_roles_or_permissions(): void
    {
        $roleCountBefore = Role::count();
        $permissionCountBefore = Permission::count();

        // Run seeder second time
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame($roleCountBefore, Role::count());
        $this->assertSame($permissionCountBefore, Permission::count());
    }

    public function test_seeding_does_not_automatically_assign_roles_to_existing_users(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'unassigned_agent@waveplus.io',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $existingUser->refresh();
        $this->assertFalse($existingUser->hasRole('BP Manager'));
        $this->assertFalse($existingUser->hasRole('Sales Responsible'));
        $this->assertFalse($existingUser->hasRole('Procurement Responsible'));
        $this->assertFalse($existingUser->hasRole('Financial Responsible'));
        $this->assertFalse($existingUser->hasRole('BP Viewer'));
    }

    public function test_seeding_preserves_custom_roles_and_unrelated_permissions(): void
    {
        $customRole = Role::firstOrCreate(['name' => 'Custom Field Agent', 'guard_name' => 'web']);
        $customRole->givePermissionTo(['read-dashboard', 'read-reports']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $customRole->refresh();
        $permissions = $customRole->permissions->pluck('name')->all();
        $this->assertContains('read-dashboard', $permissions);
        $this->assertContains('read-reports', $permissions);
    }

    public function test_bp_manager_role_allows_full_crud_exports_bank_accounts_and_batch_access(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('BP Manager');
        Sanctum::actingAs($manager);

        // Dashboard stats access
        $dashboardResponse = $this->getJson('/api/bp/dashboard-stats')->assertOk();
        $kpis = $dashboardResponse->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('service_providers', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);

        // Client CRUD + Export
        $clientRes = $this->postJson('/api/bp/clients', [
            'legal_name' => 'Acme Clients Corp',
            'status' => 'active',
        ])->assertCreated();
        $clientId = $clientRes->json('data.id');

        $this->getJson("/api/bp/clients/{$clientId}")->assertOk();
        $this->putJson("/api/bp/clients/{$clientId}", [
            'legal_name' => 'Acme Clients Corp Updated',
            'status' => 'active',
        ])->assertOk();
        $this->postJson('/api/bp/clients/export/csv', [
            'ids' => [$clientId],
        ])->assertOk();

        // Bank account management on client
        $bankRes = $this->postJson("/api/bp/clients/{$clientId}/bank-accounts", [
            'bank_name' => 'First National Bank',
            'iban' => 'FR7630006000011234567890189',
            'is_primary' => true,
        ])->assertCreated();
        $bankId = $bankRes->json('data.id');

        $this->getJson("/api/bp/bank-accounts/{$bankId}")->assertOk();

        // Bulk email batches (can view batches created by another user)
        $otherUser = User::factory()->create();
        $profile = \App\Modules\Communications\Email\Models\EmailSenderProfile::create([
            'name' => 'Support',
            'email' => 'support@waveplus.io',
            'department' => 'sales',
            'is_system' => false,
        ]);
        $batch = BpBulkEmailBatch::create([
            'sender_profile_id' => $profile->id,
            'partner_type' => 'client',
            'status' => 'completed',
            'created_by' => $otherUser->id,
            'total_targets' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'skipped_count' => 0,
        ]);

        $this->getJson('/api/bp/bulk-email/batches')->assertOk()
            ->assertJsonFragment(['id' => $batch->id]);
        $this->getJson("/api/bp/bulk-email/batches/{$batch->id}")->assertOk();

        // Supplier CRUD + Export
        $supplierRes = $this->postJson('/api/bp/suppliers', [
            'legal_name' => 'Apex Logistics Supplier',
            'status' => 'active',
        ])->assertCreated();
        $supplierId = $supplierRes->json('data.id');
        $this->getJson('/api/bp/suppliers/export/pdf')->assertOk();

        // Service Provider CRUD + Export
        $spRes = $this->postJson('/api/bp/service-providers', [
            'legal_name' => 'Metro Service Pro',
            'status' => 'active',
        ])->assertCreated();
        $spId = $spRes->json('data.id');
        $this->getJson('/api/bp/service-providers/export/pdf')->assertOk();

        // Organisation CRUD + Export
        $orgRes = $this->postJson('/api/bp/organisations', [
            'legal_name' => 'Global Org Group',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ])->assertCreated();
        $orgId = $orgRes->json('data.id');
        $this->getJson('/api/bp/organisations/export/pdf')->assertOk();

        // Deletions
        $this->deleteJson("/api/bp/clients/{$clientId}")->assertOk();
        $this->deleteJson("/api/bp/suppliers/{$supplierId}")->assertOk();
        $this->deleteJson("/api/bp/service-providers/{$spId}")->assertOk();
        $this->deleteJson("/api/bp/organisations/{$orgId}")->assertOk();
    }

    public function test_sales_responsible_role_behavior(): void
    {
        $salesUser = User::factory()->create();
        $salesUser->assignRole('Sales Responsible');
        Sanctum::actingAs($salesUser);

        // Pre-create entities using factories/models
        $client = Client::create(['legal_name' => 'Sales Target Client', 'status' => 'active']);
        $org = Organisation::create([
            'legal_name' => 'Org Parent',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['legal_name' => 'Factory Supplier', 'status' => 'active']);
        $sp = ServiceProvider::create(['legal_name' => 'Security Provider', 'status' => 'active']);

        // Dashboard stats includes clients and organisations, excludes suppliers and service providers
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);
        $this->assertArrayNotHasKey('suppliers', $kpis);
        $this->assertArrayNotHasKey('service_providers', $kpis);

        // Client CRUD: Allowed
        $this->getJson("/api/bp/clients/{$client->id}")->assertOk();
        $this->putJson("/api/bp/clients/{$client->id}", [
            'legal_name' => 'Sales Target Client Updated',
            'status' => 'active',
        ])->assertOk();
        $this->postJson('/api/bp/clients/export/csv', [
            'ids' => [$client->id],
        ])->assertOk();

        // Bank account management: Forbidden
        $this->postJson("/api/bp/clients/{$client->id}/bank-accounts", [
            'bank_name' => 'First National Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();

        // Delete client: Allowed
        $this->deleteJson("/api/bp/clients/{$client->id}")->assertOk();

        // Organisation read: Allowed
        $this->getJson("/api/bp/organisations/{$org->id}")->assertOk();

        // Organisation write/delete: Forbidden
        $this->postJson('/api/bp/organisations', [
            'legal_name' => 'Unauthorized Org',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ])->assertForbidden();
        $this->putJson("/api/bp/organisations/{$org->id}", [
            'legal_name' => 'Unauthorized Org Edit',
            'status' => 'active',
        ])->assertForbidden();
        $this->deleteJson("/api/bp/organisations/{$org->id}")->assertForbidden();

        // Supplier & Service Provider operations: Forbidden
        $this->getJson('/api/bp/suppliers')->assertForbidden();
        $this->getJson("/api/bp/suppliers/{$supplier->id}")->assertForbidden();
        $this->postJson('/api/bp/suppliers', ['legal_name' => 'Forbidden Supplier'])->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();

        $this->getJson('/api/bp/service-providers')->assertForbidden();
        $this->getJson("/api/bp/service-providers/{$sp->id}")->assertForbidden();
        $this->postJson('/api/bp/service-providers', ['legal_name' => 'Forbidden SP'])->assertForbidden();
    }

    public function test_procurement_responsible_role_behavior(): void
    {
        $procUser = User::factory()->create();
        $procUser->assignRole('Procurement Responsible');
        Sanctum::actingAs($procUser);

        $client = Client::create(['legal_name' => 'Client Org', 'status' => 'active']);
        $supplier = Supplier::create(['legal_name' => 'Industrial Parts Supplier', 'status' => 'active']);
        $sp = ServiceProvider::create(['legal_name' => 'Cleaning Service Co', 'status' => 'active']);

        // Dashboard stats includes suppliers and service providers, excludes clients and organisations
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('service_providers', $kpis);
        $this->assertArrayNotHasKey('clients', $kpis);
        $this->assertArrayNotHasKey('organisations', $kpis);

        // Supplier CRUD + Export: Allowed
        $this->getJson("/api/bp/suppliers/{$supplier->id}")->assertOk();
        $this->putJson("/api/bp/suppliers/{$supplier->id}", [
            'legal_name' => 'Industrial Parts Supplier Updated',
            'status' => 'active',
        ])->assertOk();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertOk();

        // Bank account management: Forbidden
        $this->postJson("/api/bp/suppliers/{$supplier->id}/bank-accounts", [
            'bank_name' => 'Industrial Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();

        // Delete supplier: Allowed
        $this->deleteJson("/api/bp/suppliers/{$supplier->id}")->assertOk();

        // Service Provider read: Allowed
        $this->getJson("/api/bp/service-providers/{$sp->id}")->assertOk();

        // Service Provider write/delete: Forbidden
        $this->postJson('/api/bp/service-providers', [
            'legal_name' => 'Unauthorized Service Provider',
            'status' => 'active',
        ])->assertForbidden();
        $this->putJson("/api/bp/service-providers/{$sp->id}", [
            'legal_name' => 'Unauthorized SP Update',
            'status' => 'active',
        ])->assertForbidden();
        $this->deleteJson("/api/bp/service-providers/{$sp->id}")->assertForbidden();

        // Client operations: Forbidden
        $this->getJson('/api/bp/clients')->assertForbidden();
        $this->getJson("/api/bp/clients/{$client->id}")->assertForbidden();
        $this->postJson('/api/bp/clients', ['legal_name' => 'Unauthorized Client'])->assertForbidden();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$client->id]])->assertForbidden();
    }

    public function test_financial_responsible_role_behavior(): void
    {
        $finUser = User::factory()->create();
        $finUser->assignRole('Financial Responsible');
        Sanctum::actingAs($finUser);

        $client = Client::create(['legal_name' => 'Client Ltd', 'status' => 'active']);
        $supplier = Supplier::create(['legal_name' => 'Supplier SA', 'status' => 'active']);
        $org = Organisation::create([
            'legal_name' => 'Holding Org',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ]);
        $sp = ServiceProvider::create(['legal_name' => 'Catering SP', 'status' => 'active']);

        $bankAccount = $client->bankAccounts()->create([
            'bank_name' => 'National Bank',
            'iban' => 'FR7630006000011234567890189',
            'is_primary' => true,
        ]);

        // Dashboard stats includes clients, suppliers, organisations, but excludes service providers
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);
        $this->assertArrayNotHasKey('service_providers', $kpis);

        // Can read clients, suppliers, organisations
        $this->getJson("/api/bp/clients/{$client->id}")->assertOk();
        $this->getJson("/api/bp/suppliers/{$supplier->id}")->assertOk();
        $this->getJson("/api/bp/organisations/{$org->id}")->assertOk();

        // Cannot read service providers
        $this->getJson("/api/bp/service-providers/{$sp->id}")->assertForbidden();

        // Cannot create/update/delete any partner
        $this->postJson('/api/bp/clients', ['legal_name' => 'Unauthorized'])->assertForbidden();
        $this->putJson("/api/bp/clients/{$client->id}", ['legal_name' => 'Unauthorized'])->assertForbidden();
        $this->deleteJson("/api/bp/clients/{$client->id}")->assertForbidden();

        // Cannot export
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$client->id]])->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();

        // Can read bank accounts
        $this->getJson("/api/bp/bank-accounts/{$bankAccount->id}")->assertOk();
        $this->getJson("/api/bp/clients/{$client->id}/bank-accounts")->assertOk();

        // Cannot create or update bank accounts
        $this->postJson("/api/bp/clients/{$client->id}/bank-accounts", [
            'bank_name' => 'Unauthorized Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();
        $this->putJson("/api/bp/bank-accounts/{$bankAccount->id}", [
            'bank_name' => 'Modified Bank',
        ])->assertForbidden();
    }

    public function test_bp_viewer_role_behavior(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('BP Viewer');
        Sanctum::actingAs($viewer);

        $client = Client::create(['legal_name' => 'Client One', 'status' => 'active']);
        $supplier = Supplier::create(['legal_name' => 'Supplier One', 'status' => 'active']);
        $sp = ServiceProvider::create(['legal_name' => 'SP One', 'status' => 'active']);
        $org = Organisation::create([
            'legal_name' => 'Org One',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ]);

        $bankAccount = $client->bankAccounts()->create([
            'bank_name' => 'National Bank',
            'iban' => 'FR7630006000011234567890189',
            'is_primary' => true,
        ]);

        // Dashboard stats: Allowed with all 4 types
        $kpis = $this->getJson('/api/bp/dashboard-stats')->assertOk()->json('data.kpis');
        $this->assertArrayHasKey('clients', $kpis);
        $this->assertArrayHasKey('suppliers', $kpis);
        $this->assertArrayHasKey('service_providers', $kpis);
        $this->assertArrayHasKey('organisations', $kpis);

        // Can read all four partner types
        $this->getJson("/api/bp/clients/{$client->id}")->assertOk();
        $this->getJson("/api/bp/suppliers/{$supplier->id}")->assertOk();
        $this->getJson("/api/bp/service-providers/{$sp->id}")->assertOk();
        $this->getJson("/api/bp/organisations/{$org->id}")->assertOk();

        // Cannot create, update, delete, or export
        $this->postJson('/api/bp/clients', ['legal_name' => 'New Client'])->assertForbidden();
        $this->putJson("/api/bp/clients/{$client->id}", ['legal_name' => 'Edit Client'])->assertForbidden();
        $this->deleteJson("/api/bp/clients/{$client->id}")->assertForbidden();
        $this->postJson('/api/bp/clients/export/csv', ['ids' => [$client->id]])->assertForbidden();
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();

        // Cannot read or manage bank accounts
        $this->getJson("/api/bp/bank-accounts/{$bankAccount->id}")->assertForbidden();
        $this->getJson("/api/bp/clients/{$client->id}/bank-accounts")->assertForbidden();
        $this->postJson("/api/bp/clients/{$client->id}/bank-accounts", [
            'bank_name' => 'New Bank',
            'iban' => 'FR7630006000011234567890189',
        ])->assertForbidden();

        // Cannot send emails
        $this->postJson("/api/bp/clients/{$client->id}/send-email", [
            'to' => [['email' => 'viewer_test@example.com']],
            'subject' => 'Hello',
            'body_html' => '<p>Hello</p>',
        ])->assertForbidden();
    }

    public function test_role_templates_manageable_via_roles_administration_api(): void
    {
        $superAdmin = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $superAdmin->assignRole('Super Admin');
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/admin/roles')->assertOk();
        $roles = collect($response->json('data'));

        $roleNames = $roles->pluck('name')->all();

        $this->assertContains('BP Manager', $roleNames);
        $this->assertContains('Sales Responsible', $roleNames);
        $this->assertContains('Procurement Responsible', $roleNames);
        $this->assertContains('Financial Responsible', $roleNames);
        $this->assertContains('BP Viewer', $roleNames);

        $bpManager = $roles->firstWhere('name', 'BP Manager');
        $this->assertNotNull($bpManager);
        $bpManagerPerms = collect($bpManager['permissions'])->pluck('name')->all();
        $this->assertContains('manage-bp-bank-accounts', $bpManagerPerms);
        $this->assertContains('view-all-bp-email-batches', $bpManagerPerms);
    }
}
