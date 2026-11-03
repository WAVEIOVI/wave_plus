<?php

declare(strict_types=1);

namespace Tests\Feature\ClientAssets;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientEquipmentTransfer;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use App\Modules\ClientAssets\Models\EquipmentLocationHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class ClientAssetsIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Client $otherClient;

    private BpAddress $address;

    private EquipmentClass $eqClass;

    private EquipmentBlueprint $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Acme Assets SARL',
            'commercial_name' => 'Acme Assets',
            'status' => 'active',
        ]);
        $this->otherClient = Client::create([
            'legal_name' => 'Other Client SARL',
            'status' => 'active',
        ]);
        $this->address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->client->id,
            'type' => 'operational_site',
            'address_street' => ['en' => '10 Industrial Avenue'],
            'city' => ['en' => 'Tunis'],
            'country' => 'Tunisia',
        ]);
        $this->eqClass = EquipmentClass::create([
            'code' => 'extinguisher',
            'name' => ['en' => 'Extinguisher'],
            'status' => 'active',
        ]);
        $this->blueprint = EquipmentBlueprint::create([
            'code' => 'BP-CA-'.rand(1000, 9999),
            'name' => ['en' => 'ABC 6KG'],
            'status' => 'active',
            'equipment_class_id' => $this->eqClass->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_client_assets(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/client-assets/sites')->assertForbidden();
        $this->getJson('/api/client-assets/equipment')->assertForbidden();
        $this->postJson('/api/client-assets/sites', [
            'client_id' => $this->client->id,
            'name' => 'Blocked',
        ])->assertForbidden();
    }

    public function test_reader_can_list_but_cannot_create_or_transfer(): void
    {
        Permission::findOrCreate('read-ClientAssets', 'web');
        $reader = User::factory()->create();
        $reader->givePermissionTo(['read-ClientAssets']);
        Sanctum::actingAs($reader);

        $this->getJson('/api/client-assets/sites')->assertOk();
        $this->getJson('/api/client-assets/equipment')->assertOk();
        $this->postJson('/api/client-assets/sites', [
            'client_id' => $this->client->id,
            'bp_address_id' => $this->address->id,
            'name' => 'Reader Site',
        ])->assertForbidden();
    }

    public function test_site_rejects_address_from_another_client(): void
    {
        $otherAddress = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->otherClient->id,
            'type' => 'operational_site',
            'address_street' => ['en' => '99 Other Street'],
            'city' => ['en' => 'Sfax'],
            'country' => 'Tunisia',
        ]);

        $this->postJson('/api/client-assets/sites', [
            'client_id' => $this->client->id,
            'bp_address_id' => $otherAddress->id,
            'name' => 'Mismatched',
        ])->assertStatus(422)->assertJsonValidationErrors(['bp_address_id']);
    }

    public function test_area_cannot_become_its_own_descendant_parent(): void
    {
        $site = $this->createSite();
        $zone = $this->postJson("/api/client-assets/sites/{$site->id}/areas", [
            'name' => 'Zone',
            'area_type' => 'zone',
        ])->assertCreated()->json('data');
        $room = $this->postJson("/api/client-assets/sites/{$site->id}/areas", [
            'parent_area_id' => $zone['id'],
            'name' => 'Room',
            'area_type' => 'room',
        ])->assertCreated()->json('data');

        $this->putJson("/api/client-assets/areas/{$zone['id']}", [
            'parent_area_id' => $room['id'],
        ])->assertStatus(422)->assertJsonValidationErrors(['parent_area_id']);
    }

    public function test_location_change_via_update_writes_transfer_and_history(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $eqId = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client->id,
            'site_id' => $siteA->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
            'lifecycle_status' => 'operational',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/client-assets/equipment/{$eqId}", [
            'site_id' => $siteB->id,
            'area_id' => null,
        ])->assertOk()->assertJsonPath('data.site_id', $siteB->id);

        $this->assertSame(1, ClientEquipmentTransfer::where('equipment_id', $eqId)->count());
        $this->assertNotNull(
            EquipmentLocationHistory::where('equipment_id', $eqId)->where('site_id', $siteA->id)->whereNotNull('ended_at')->first()
        );
        $this->assertNotNull(
            EquipmentLocationHistory::where('equipment_id', $eqId)->where('site_id', $siteB->id)->whereNull('ended_at')->first()
        );
    }

    public function test_cannot_reassign_equipment_or_occupied_site_to_another_client(): void
    {
        $site = $this->createSite();
        $eqId = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client->id,
            'site_id' => $site->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/client-assets/equipment/{$eqId}", [
            'client_id' => $this->otherClient->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['client_id']);

        $this->putJson("/api/client-assets/sites/{$site->id}", [
            'client_id' => $this->otherClient->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['client_id']);
    }

    public function test_transfer_to_other_client_site_is_rejected_and_location_unchanged(): void
    {
        $site = $this->createSite();
        $foreignSite = ClientSite::create([
            'client_id' => $this->otherClient->id,
            'name' => 'Foreign Site',
            'status' => 'active',
        ]);
        $eqId = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client->id,
            'site_id' => $site->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/client-assets/equipment/{$eqId}/transfer", [
            'to_site_id' => $foreignSite->id,
            'reason' => 'Illegal move',
        ])->assertStatus(422);

        $this->assertSame($site->id, ClientEquipment::find($eqId)->site_id);
        $this->assertSame(0, ClientEquipmentTransfer::where('equipment_id', $eqId)->count());
    }

    public function test_public_qr_scan_stays_public_and_does_not_leak_unrelated_rows(): void
    {
        $eqId = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
            'qr_code' => 'QR-PUBLIC-001',
        ])->assertCreated()->json('data.id');

        $code = ClientEquipment::find($eqId)->equipment_code;

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/ca/scan/eq/QR-PUBLIC-001')
            ->assertOk()
            ->assertJsonPath('data.equipment_code', $code)
            ->assertJsonMissingPath('data.client_id');
    }

    private function createSite(string $name = 'Main Site'): ClientSite
    {
        $id = $this->postJson('/api/client-assets/sites', [
            'client_id' => $this->client->id,
            'bp_address_id' => $this->address->id,
            'name' => $name,
        ])->assertCreated()->json('data.id');

        return ClientSite::findOrFail($id);
    }
}
