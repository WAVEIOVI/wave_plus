<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessPartners;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessPartnerCrudAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_unauthorized_user_cannot_access_business_partners(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/bp/clients')->assertForbidden();
        $this->postJson('/api/bp/clients', ['legal_name' => 'Acme'])->assertForbidden();
    }

    public function test_read_business_partners_permission_only_controls_module_visibility_not_entities(): void
    {
        $this->actingWithPermissions(['read-BusinessPartners']);

        // Allowed through bp.access middleware but denied by entity policies
        $this->getJson('/api/bp/clients')->assertForbidden();
        $this->getJson('/api/bp/suppliers')->assertForbidden();
        $this->getJson('/api/bp/service-providers')->assertForbidden();
        $this->getJson('/api/bp/organisations')->assertForbidden();
    }

    public function test_reader_with_read_clients_can_list_but_cannot_create(): void
    {
        $this->actingWithPermissions(['read-clients']);

        $this->getJson('/api/bp/clients')->assertOk();

        $this->postJson('/api/bp/clients', [
            'legal_name' => 'Acme SARL',
        ])->assertForbidden();
    }

    public function test_can_create_and_search_clients(): void
    {
        $this->actingAsSuperAdmin();

        $create = $this->postJson('/api/bp/clients', [
            'legal_name' => 'Wave Industrial SARL',
            'commercial_name' => ['en' => 'Wave'],
            'primary_email' => 'wave@example.com',
            'status' => 'active',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.legal_name.en', 'Wave Industrial SARL');
        $this->assertNotEmpty($create->json('data.client_id'));

        $this->postJson('/api/bp/clients', [
            'legal_name' => 'Other Client',
            'status' => 'prospect',
        ])->assertCreated();

        $search = $this->getJson('/api/bp/clients?q=Wave');
        $search->assertOk();
        $this->assertSame(1, $search->json('total'));
        $this->assertSame('Wave Industrial SARL', $search->json('data.0.legal_name.en'));
    }

    public function test_validation_failure_on_client_create(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/bp/clients', [
            'legal_name' => '',
            'primary_email' => 'not-an-email',
        ])->assertStatus(422);
    }

    public function test_options_endpoint_returns_lightweight_payload(): void
    {
        $this->actingAsSuperAdmin();

        Client::create(['legal_name' => 'Lookup Client', 'status' => 'active']);

        $response = $this->getJson('/api/bp/clients/options?q=Lookup');
        $response->assertOk();
        $response->assertJsonPath('data.0.legal_name', 'Lookup Client');
        $this->assertArrayHasKey('code', $response->json('data.0'));
        $this->assertArrayNotHasKey('credit_limit', $response->json('data.0'));
    }

    public function test_legacy_unbounded_list_is_capped(): void
    {
        $this->actingAsSuperAdmin();

        for ($i = 0; $i < 5; $i++) {
            Client::create(['legal_name' => "Client {$i}", 'status' => 'active']);
        }

        $response = $this->getJson('/api/bp/clients?itemsPerPage=-1');
        $response->assertOk();
        $this->assertSame(5, $response->json('total'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_supplier_crud_round_trip(): void
    {
        $this->actingAsSuperAdmin();

        $created = $this->postJson('/api/bp/suppliers', [
            'legal_name' => 'Atlas Supplies',
            'supplier_category' => 'distributor',
            'status' => 'active',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->putJson("/api/bp/suppliers/{$id}", [
            'legal_name' => 'Atlas Supplies Intl',
        ])->assertOk()->assertJsonPath('data.legal_name.en', 'Atlas Supplies Intl');

        $this->deleteJson("/api/bp/suppliers/{$id}")->assertOk();
        $this->assertNotNull(Supplier::withTrashed()->find($id)?->deleted_at);
    }

    public function test_organisation_is_soft_deleted(): void
    {
        $this->actingAsSuperAdmin();

        $created = $this->postJson('/api/bp/organisations', [
            'legal_name' => 'Municipality of Tunis',
            'organisation_type' => 'municipality',
            'scope' => 'local',
        ])->assertCreated();

        $id = $created->json('data.id');
        $this->deleteJson("/api/bp/organisations/{$id}")->assertOk();
        $this->assertNotNull(Organisation::withTrashed()->find($id)?->deleted_at);
        $this->getJson("/api/bp/organisations/{$id}")->assertNotFound();
    }

    public function test_address_belongs_to_owner_and_primary_flag_is_unique(): void
    {
        $this->actingAsSuperAdmin();

        $client = Client::create(['legal_name' => 'Primary HQ Client', 'status' => 'active']);

        $first = $this->postJson("/api/bp/clients/{$client->id}/addresses", [
            'type' => 'headquarters',
            'is_primary' => true,
            'address_street' => ['en' => '1 Avenue Habib Bourguiba'],
            'city' => ['en' => 'Tunis'],
            'postcode' => '1000',
            'country' => 'Tunisia',
        ])->assertCreated();

        $second = $this->postJson("/api/bp/clients/{$client->id}/addresses", [
            'type' => 'billing',
            'is_primary' => true,
            'address_street' => ['en' => '2 Rue de Marseille'],
            'city' => ['en' => 'Tunis'],
            'country' => 'Tunisia',
        ])->assertCreated();

        $this->assertFalse(BpAddress::find($first->json('data.id'))->is_primary);
        $this->assertTrue(BpAddress::find($second->json('data.id'))->is_primary);

        $client->refresh();
        $this->assertSame('2 Rue de Marseille', $client->getTranslation('hq_address', 'en'));
        $this->assertSame('Tunis', $client->getTranslation('hq_city', 'en'));
        $this->assertSame('Tunisia', $client->getTranslation('hq_country', 'en'));
    }

    public function test_cannot_create_address_for_missing_owner(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/bp/clients/999999/addresses', [
            'address_street' => ['en' => 'Nowhere'],
            'city' => ['en' => 'Tunis'],
        ])->assertNotFound();
    }

    public function test_cannot_link_contact_and_address_across_partners(): void
    {
        $this->actingAsSuperAdmin();

        $clientA = Client::create(['legal_name' => 'Alpha', 'status' => 'active']);
        $clientB = Client::create(['legal_name' => 'Beta', 'status' => 'active']);

        $contact = BpContact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $clientA->id,
            'first_name' => ['en' => 'Sara'],
            'last_name' => ['en' => 'Ben Ali'],
            'email' => 'sara@example.com',
        ]);

        $address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $clientB->id,
            'type' => 'branch',
            'address_street' => ['en' => 'Other street'],
            'city' => ['en' => 'Sfax'],
            'country' => 'Tunisia',
        ]);

        $this->postJson("/api/bp/contacts/{$contact->id}/addresses", [
            'bp_address_id' => $address->id,
        ])->assertStatus(422);
    }

    public function test_contact_primary_location_is_unique_per_contact(): void
    {
        $this->actingAsSuperAdmin();

        $client = Client::create(['legal_name' => 'Link Client', 'status' => 'active']);
        $contact = BpContact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $client->id,
            'first_name' => ['en' => 'Nour'],
            'last_name' => ['en' => 'Kacem'],
        ]);
        $addr1 = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $client->id,
            'address_street' => ['en' => 'Street 1'],
            'city' => ['en' => 'Tunis'],
        ]);
        $addr2 = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $client->id,
            'address_street' => ['en' => 'Street 2'],
            'city' => ['en' => 'Tunis'],
        ]);

        $this->postJson("/api/bp/contacts/{$contact->id}/addresses", [
            'bp_address_id' => $addr1->id,
            'is_primary_location' => true,
        ])->assertOk();

        $this->postJson("/api/bp/contacts/{$contact->id}/addresses", [
            'bp_address_id' => $addr2->id,
            'is_primary_location' => true,
        ])->assertOk();

        $this->assertEquals(0, (int) $contact->addresses()->where('bp_addresses.id', $addr1->id)->first()->pivot->is_primary_location);
        $this->assertEquals(1, (int) $contact->addresses()->where('bp_addresses.id', $addr2->id)->first()->pivot->is_primary_location);
    }

    public function test_logo_source_switches_clear_the_previous_authoritative_logo(): void
    {
        $this->actingAsSuperAdmin();
        Storage::fake(config('media-library.disk_name'));

        $client = Client::create([
            'legal_name' => 'Logo Client',
            'status' => 'active',
            'logo_source' => 'brandfetch',
            'logo_domain' => 'old.example',
            'logo_external_url' => 'https://old.example/logo.png',
        ]);
        $client->addMedia(UploadedFile::fake()->image('old-logo.png'))->toMediaCollection('logo');

        $this->putJson("/api/bp/clients/{$client->id}", [
            'logo_source' => 'upload',
        ])->assertUnprocessable();

        $this->putJson("/api/bp/clients/{$client->id}", [
            'logo_source' => 'logo_dev',
        ])->assertUnprocessable();

        $client->refresh();
        $this->assertCount(1, $client->getMedia('logo'));
        $this->assertSame('brandfetch', $client->logo_source);
        $this->assertSame('https://old.example/logo.png', $client->logo_external_url);

        $this->putJson("/api/bp/clients/{$client->id}", [
            'logo_source' => 'logo_dev',
            'logo_domain' => 'new.example',
            'logo_external_url' => 'https://cdn.new.example/logo.png',
        ])->assertOk();

        $client->refresh();
        $this->assertCount(0, $client->getMedia('logo'));
        $this->assertSame('logo_dev', $client->logo_source);
        $this->assertSame('new.example', $client->logo_domain);
        $this->assertSame('https://cdn.new.example/logo.png', $client->logo_external_url);
        $this->assertNull($client->logo_path);

        $this->put("/api/bp/clients/{$client->id}", [
            'logo' => UploadedFile::fake()->image('uploaded-logo.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $client->refresh();
        $this->assertCount(1, $client->getMedia('logo'));
        $this->assertSame('upload', $client->logo_source);
        $this->assertNull($client->logo_domain);
        $this->assertNull($client->logo_external_url);

        $this->putJson("/api/bp/clients/{$client->id}", [
            'logo_source' => 'initials',
        ])->assertOk();

        $client->refresh();
        $this->assertCount(0, $client->getMedia('logo'));
        $this->assertSame('initials', $client->logo_source);
        $this->assertNull($client->logo_domain);
        $this->assertNull($client->logo_external_url);
        $this->assertNull($client->logo_path);
    }

    public function test_document_upload_and_update_accept_json_encoded_metadata(): void
    {
        $this->actingAsSuperAdmin();
        Storage::fake(config('media-library.disk_name'));

        $client = Client::create(['legal_name' => 'Document Client', 'status' => 'active']);

        $created = $this->post("/api/bp/clients/{$client->id}/documents", [
            'type' => 'other',
            'title' => 'Client document',
            'metadata' => json_encode(['category' => 'legal']),
            'file' => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"),
        ], ['Accept' => 'application/json']);

        $created->assertCreated();
        $created->assertJsonPath('data.metadata.category', 'legal');
        $created->assertJsonPath('data.file_name', 'document.pdf');
        $documentId = $created->json('data.id');
        $mediaId = DB::table('media')->value('id');
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $mediaId);

        $this->post("/api/bp/documents/{$documentId}", [
            '_method' => 'PUT',
            'metadata' => json_encode(['category' => 'updated']),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.metadata.category', 'updated');
    }

    public function test_bp_multilingual_fields_for_partners_addresses_and_contacts(): void
    {
        $this->actingAsSuperAdmin();

        // 1. Create client with multilingual legal_name
        $clientRes = $this->postJson('/api/bp/clients', [
            'legal_name' => [
                'en' => 'TechnoGuard International Ltd',
                'fr' => 'TechnoGuard International SARL',
                'ar' => 'تكنوغارد الدولية',
            ],
            'commercial_name' => ['en' => 'TechnoGuard'],
            'status' => 'active',
        ])->assertCreated();

        $clientRes->assertJsonPath('data.legal_name.en', 'TechnoGuard International Ltd');
        $clientRes->assertJsonPath('data.legal_name.fr', 'TechnoGuard International SARL');
        $clientRes->assertJsonPath('data.legal_name.ar', 'تكنوغارد الدولية');
        $clientId = $clientRes->json('data.id');

        // 2. Create BP address with multilingual label, street, street2, city, state, country
        $addrRes = $this->postJson("/api/bp/clients/{$clientId}/addresses", [
            'type' => 'headquarters',
            'is_primary' => true,
            'label' => ['en' => 'Main Office', 'fr' => 'Bureau Principal', 'ar' => 'المكتب الرئيسي'],
            'address_street' => ['en' => 'Lake View 10', 'fr' => 'Vue du Lac 10', 'ar' => 'إطلالة البحيرة 10'],
            'address_street2' => ['en' => 'Floor 3', 'fr' => '3ème étage', 'ar' => 'الطابق 3'],
            'city' => ['en' => 'Tunis', 'fr' => 'Tunis', 'ar' => 'تونس'],
            'state' => ['en' => 'Tunis', 'fr' => 'Tunis', 'ar' => 'تونس'],
            'country' => ['en' => 'Tunisia', 'fr' => 'Tunisie', 'ar' => 'تونس'],
            'postcode' => '1053',
        ])->assertCreated();

        $addrRes->assertJsonPath('data.label.fr', 'Bureau Principal');
        $addrRes->assertJsonPath('data.address_street.en', 'Lake View 10');
        $addrRes->assertJsonPath('data.address_street2.fr', '3ème étage');
        $addrRes->assertJsonPath('data.city.ar', 'تونس');
        $addrRes->assertJsonPath('data.country.en', 'Tunisia');
        $addrRes->assertJsonPath('data.country.fr', 'Tunisie');

        // 3. Create BP contact with multilingual first_name, last_name
        $contactRes = $this->postJson("/api/bp/clients/{$clientId}/contacts", [
            'first_name' => ['en' => 'Karim', 'fr' => 'Karim', 'ar' => 'كريم'],
            'last_name' => ['en' => 'Ben Salah', 'fr' => 'Ben Salah', 'ar' => 'بن صالح'],
            'is_primary' => true,
            'email' => 'k.bensalah@technoguard.tn',
        ])->assertCreated();

        $contactRes->assertJsonPath('data.first_name.en', 'Karim');
        $contactRes->assertJsonPath('data.first_name.ar', 'كريم');
        $contactRes->assertJsonPath('data.last_name.fr', 'Ben Salah');
        $contactRes->assertJsonPath('data.full_name', 'Karim Ben Salah');
    }

    public function test_hq_address_synchronization_and_harmonized_types(): void
    {
        $this->actingAsSuperAdmin();

        $client = Client::create([
            'legal_name' => ['en' => 'Harmonized Facility Client', 'fr' => 'Client Installation'],
            'status' => 'active',
        ]);

        // 1. Create first address with harmonized type 'factory'
        $addr1 = $this->postJson("/api/bp/clients/{$client->id}/addresses", [
            'type' => 'factory',
            'is_primary' => true,
            'label' => ['en' => 'Main Manufacturing Plant'],
            'address_street' => ['en' => 'Industrial Area 5'],
            'city' => ['en' => 'Ben Arous'],
            'postcode' => '2013',
            'country' => ['en' => 'Tunisia', 'fr' => 'Tunisie'],
        ])->assertCreated();

        $client->refresh();
        $this->assertSame('Industrial Area 5', $client->getTranslation('hq_address', 'en'));
        $this->assertSame('Ben Arous', $client->getTranslation('hq_city', 'en'));
        $this->assertSame('2013', $client->hq_postcode);
        $this->assertSame('Tunisia', $client->getTranslation('hq_country', 'en'));

        // 2. Create second address with harmonized type 'datacenter' as primary
        $addr2 = $this->postJson("/api/bp/clients/{$client->id}/addresses", [
            'type' => 'datacenter',
            'is_primary' => true,
            'label' => ['en' => 'Cloud Data Center'],
            'address_street' => ['en' => 'Technopark Block B'],
            'city' => ['en' => 'El Ghazala'],
            'postcode' => '2088',
            'country' => ['en' => 'Tunisia'],
        ])->assertCreated();

        $client->refresh();
        // Client HQ should now reflect the new primary address (Data Center)
        $this->assertSame('Technopark Block B', $client->getTranslation('hq_address', 'en'));
        $this->assertSame('El Ghazala', $client->getTranslation('hq_city', 'en'));
        $this->assertSame('2088', $client->hq_postcode);

        // 3. Delete the second address
        $this->deleteJson("/api/bp/addresses/{$addr2->json('data.id')}")->assertOk();

        // 4. Client HQ should fall back to the remaining address
        $client->refresh();
        $this->assertSame('Industrial Area 5', $client->getTranslation('hq_address', 'en'));
        $this->assertSame('Ben Arous', $client->getTranslation('hq_city', 'en'));
    }

    public function test_dashboard_recent_partners_returns_translated_legal_name_values(): void
    {
        $this->actingAsSuperAdmin();

        Client::create([
            'legal_name' => [
                'en' => 'Southern Chemical Industries LLC',
                'fr' => 'Industries Chimiques du Sud SARL',
            ],
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/bp/dashboard-stats');

        $response->assertOk()
            ->assertJsonPath('data.recent_partners.0.name.en', 'Southern Chemical Industries LLC')
            ->assertJsonPath('data.recent_partners.0.name.fr', 'Industries Chimiques du Sud SARL');
    }

    public function test_dashboard_stats_requires_read_bp_dashboard_permission(): void
    {
        // User with only read-clients cannot view dashboard stats
        $this->actingWithPermissions(['read-clients']);
        $this->getJson('/api/bp/dashboard-stats')->assertForbidden();

        // User with only read-BusinessPartners cannot view dashboard stats
        $this->actingWithPermissions(['read-BusinessPartners']);
        $this->getJson('/api/bp/dashboard-stats')->assertForbidden();

        // User with read-bp-dashboard can view
        $this->actingWithPermissions(['read-bp-dashboard']);
        $this->getJson('/api/bp/dashboard-stats')->assertOk();
    }

    public function test_bank_account_authorization_requires_granular_permissions(): void
    {
        $client = Client::create(['legal_name' => 'Bank Client', 'status' => 'active']);

        // User with only read-clients cannot read bank accounts
        $this->actingWithPermissions(['read-clients']);
        $this->getJson("/api/bp/clients/{$client->id}/bank-accounts")->assertForbidden();

        // User with read-clients AND read-bp-bank-accounts can view bank accounts
        $this->actingWithPermissions(['read-clients', 'read-bp-bank-accounts']);
        $this->getJson("/api/bp/clients/{$client->id}/bank-accounts")->assertOk();

        // But cannot create bank account
        $this->postJson("/api/bp/clients/{$client->id}/bank-accounts", [
            'bank_name' => 'BIAT',
            'iban' => 'TN5912345678901234567890',
        ])->assertForbidden();

        // User with edit-clients AND manage-bp-bank-accounts can create bank account
        $this->actingWithPermissions(['edit-clients', 'manage-bp-bank-accounts']);
        $create = $this->postJson("/api/bp/clients/{$client->id}/bank-accounts", [
            'bank_name' => 'BIAT',
            'iban' => 'TN5912345678901234567890',
        ])->assertCreated();

        $bankAccountId = $create->json('data.id');

        // Can view the created bank account with read-bp-bank-accounts + read-clients
        $this->actingWithPermissions(['read-clients', 'read-bp-bank-accounts']);
        $this->getJson("/api/bp/bank-accounts/{$bankAccountId}")->assertOk();

        // Cannot update if missing manage-bp-bank-accounts
        $this->putJson("/api/bp/bank-accounts/{$bankAccountId}", [
            'bank_name' => 'BIAT Updated',
        ])->assertForbidden();

        // Can update with manage-bp-bank-accounts + edit-clients
        $this->actingWithPermissions(['edit-clients', 'manage-bp-bank-accounts']);
        $this->putJson("/api/bp/bank-accounts/{$bankAccountId}", [
            'bank_name' => 'BIAT Updated',
        ])->assertOk();

        // Can delete with manage-bp-bank-accounts + edit-clients
        $this->deleteJson("/api/bp/bank-accounts/{$bankAccountId}")->assertOk();
    }

    public function test_bulk_email_batches_authorization(): void
    {
        $profile = \App\Modules\Communications\Email\Models\EmailSenderProfile::create([
            'name' => 'WAVE System',
            'email' => 'system@waveplus.xyz',
            'department' => 'System',
            'is_system' => true,
            'is_active' => true,
        ]);

        $user1 = $this->actingWithPermissions(['read-clients']);
        $batch1 = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::create([
            'partner_type' => 'client',
            'sender_profile_id' => $profile->id,
            'created_by_user_id' => $user1->id,
            'total_count' => 1,
            'status' => 'completed',
        ]);

        $user2 = $this->actingWithPermissions(['read-clients']);
        $batch2 = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::create([
            'partner_type' => 'client',
            'sender_profile_id' => $profile->id,
            'created_by_user_id' => $user2->id,
            'total_count' => 1,
            'status' => 'completed',
        ]);

        // User 2 can view own batch
        $this->getJson("/api/bp/bulk-email/batches/{$batch2->id}")->assertOk();
        // User 2 cannot view user 1's batch
        $this->getJson("/api/bp/bulk-email/batches/{$batch1->id}")->assertForbidden();

        // In listBatches, user 2 only sees own batch
        $list = $this->getJson('/api/bp/bulk-email/batches')->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame($batch2->id, $list->json('data.0.id'));

        // User with view-all-bp-email-batches can view user 1's batch and see all in list
        $this->actingWithPermissions(['read-clients', 'view-all-bp-email-batches']);
        $this->getJson("/api/bp/bulk-email/batches/{$batch1->id}")->assertOk();
        $allList = $this->getJson('/api/bp/bulk-email/batches')->assertOk();
        $this->assertCount(2, $allList->json('data'));
    }

    public function test_export_permissions_for_all_partner_types(): void
    {
        $supplier = Supplier::create(['legal_name' => 'Export Supplier', 'status' => 'active']);
        $serviceProvider = \App\Modules\BusinessPartners\Models\ServiceProvider::create(['legal_name' => 'Export SP', 'status' => 'active']);
        $organisation = Organisation::create([
            'legal_name' => 'Export Org',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'status' => 'active',
        ]);

        // Without export permissions, export endpoints are forbidden
        $this->actingWithPermissions(['read-suppliers', 'read-service-providers', 'read-organisations']);
        $this->getJson('/api/bp/suppliers/export/pdf')->assertForbidden();
        $this->getJson("/api/bp/suppliers/{$supplier->id}/export/pdf")->assertForbidden();
        $this->getJson('/api/bp/service-providers/export/pdf')->assertForbidden();
        $this->getJson("/api/bp/service-providers/{$serviceProvider->id}/export/pdf")->assertForbidden();
        $this->getJson('/api/bp/organisations/export/pdf')->assertForbidden();
        $this->getJson("/api/bp/organisations/{$organisation->id}/export/pdf")->assertForbidden();
    }

    public function test_super_admin_bypasses_all_bp_authorization_checks(): void
    {
        $this->actingAsSuperAdmin();

        $client = Client::create(['legal_name' => 'Super Admin Client', 'status' => 'active']);

        // Dashboard
        $this->getJson('/api/bp/dashboard-stats')->assertOk();

        // List & view clients
        $this->getJson('/api/bp/clients')->assertOk();
        $this->getJson("/api/bp/clients/{$client->id}")->assertOk();

        // Bank accounts
        $this->getJson("/api/bp/clients/{$client->id}/bank-accounts")->assertOk();
        $bank = $this->postJson("/api/bp/clients/{$client->id}/bank-accounts", [
            'bank_name' => 'Admin Bank',
            'iban' => 'TN5999999999999999999999',
        ])->assertCreated();

        $this->getJson("/api/bp/bank-accounts/{$bank->json('data.id')}")->assertOk();
        $this->deleteJson("/api/bp/bank-accounts/{$bank->json('data.id')}")->assertOk();
    }

    public function test_dashboard_statistics_scoped_to_accessible_partner_types(): void
    {
        Client::create(['legal_name' => 'Accessible Client', 'status' => 'active']);
        Supplier::create(['legal_name' => 'Hidden Supplier', 'status' => 'active']);

        // User can only read clients and dashboard
        $this->actingWithPermissions(['read-bp-dashboard', 'read-clients']);

        $res = $this->getJson('/api/bp/dashboard-stats')->assertOk();
        $this->assertArrayHasKey('clients', $res->json('data.kpis'));
        $this->assertArrayNotHasKey('suppliers', $res->json('data.kpis'));

        // Recent partners must only contain clients, no suppliers
        $recent = collect($res->json('data.recent_partners'));
        $this->assertTrue($recent->every(fn ($p) => $p['type'] === 'client'));
        $this->assertTrue($recent->contains(fn ($p) => ($p['name']['en'] ?? null) === 'Accessible Client'));
    }

    public function test_contextual_email_preparation_and_sending_authorization(): void
    {
        $client = Client::create(['legal_name' => 'Email Client', 'primary_email' => 'client@test.com', 'status' => 'active']);

        // 1. User with create-email-messages but no read-clients cannot prepare or send
        $this->actingWithPermissions(['read-BusinessPartners', 'create-email-messages']);
        $this->getJson("/api/bp/clients/{$client->id}/contextual-email")->assertForbidden();
        $this->postJson("/api/bp/clients/{$client->id}/send-email", [
            'to' => [['email' => 'to@test.com']],
            'subject' => 'Hello',
            'body_html' => '<p>Hello</p>',
        ])->assertForbidden();

        // 2. User with read-clients but without create-email-messages cannot prepare or send
        $this->actingWithPermissions(['read-clients']);
        $this->getJson("/api/bp/clients/{$client->id}/contextual-email")->assertForbidden();
        $this->postJson("/api/bp/clients/{$client->id}/send-email", [
            'to' => [['email' => 'to@test.com']],
            'subject' => 'Hello',
            'body_html' => '<p>Hello</p>',
        ])->assertForbidden();

        // 3. User without create-email-messages cannot invoke bulk preview or bulk send
        $this->postJson('/api/bp/clients/bulk-email/preview', [
            'partner_ids' => [$client->id],
        ])->assertForbidden();
        $this->postJson('/api/bp/clients/bulk-email', [
            'partner_ids' => [$client->id],
        ])->assertForbidden();
    }

    public function test_bulk_email_batch_partner_type_verification(): void
    {
        $profile = \App\Modules\Communications\Email\Models\EmailSenderProfile::create([
            'name' => 'WAVE System 2',
            'email' => 'system2@waveplus.xyz',
            'department' => 'System',
            'is_system' => true,
            'is_active' => true,
        ]);

        $supplierBatch = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::create([
            'partner_type' => 'supplier',
            'sender_profile_id' => $profile->id,
            'total_count' => 1,
            'status' => 'completed',
        ]);

        // User with view-all-bp-email-batches and read-clients, but lacking read-suppliers
        $this->actingWithPermissions(['read-clients', 'view-all-bp-email-batches']);

        // Cannot view supplier batch
        $this->getJson("/api/bp/bulk-email/batches/{$supplierBatch->id}")->assertForbidden();

        // Requesting supplier batches is forbidden
        $this->getJson('/api/bp/bulk-email/batches?partner_type=supplier')->assertForbidden();
    }

    public function test_legacy_email_endpoint_authorization(): void
    {
        // 1. Without create-email-messages, legacy sendEmailList is forbidden
        $this->actingWithPermissions(['read-clients']);
        $this->postJson('/api/bp/clients/send-email', [
            'emailTo' => ['test@example.com'],
            'subject' => 'Test List',
            'message' => 'Please find attached',
        ])->assertForbidden();

        // 2. Without read-clients, legacy sendEmailList is forbidden even with create-email-messages
        $this->actingWithPermissions(['create-email-messages', 'read-BusinessPartners']);
        $this->postJson('/api/bp/clients/send-email', [
            'emailTo' => ['test@example.com'],
            'subject' => 'Test List',
            'message' => 'Please find attached',
        ])->assertForbidden();
    }

    public function test_bank_account_details_hidden_from_partner_resource_without_permissions(): void
    {
        $client = Client::create(['legal_name' => 'Bank Protect Client', 'status' => 'active']);
        \App\Modules\BusinessPartners\Models\BpBankAccount::create([
            'bankable_type' => Client::class,
            'bankable_id' => $client->id,
            'bank_name' => 'Secret Bank',
            'account_number' => 'ACC123456789',
        ]);

        // User with only read-clients
        $this->actingWithPermissions(['read-clients']);
        $res = $this->getJson("/api/bp/clients/{$client->id}")->assertOk();
        $this->assertEmpty($res->json('data.bank_accounts'));

        // User with read-clients AND read-bp-bank-accounts
        $this->actingWithPermissions(['read-clients', 'read-bp-bank-accounts']);
        $resWithBank = $this->getJson("/api/bp/clients/{$client->id}")->assertOk();
        $this->assertNotEmpty($resWithBank->json('data.bank_accounts'));
        $this->assertSame('Secret Bank', $resWithBank->json('data.bank_accounts.0.bank_name'));
    }
}
