<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\BlueprintInterventionCycle;
use App\Modules\Catalog\Models\BlueprintOption;
use App\Modules\Catalog\Models\BlueprintOptionValue;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use App\Modules\ClientAssets\Models\EquipmentLocationHistory;
use App\Modules\ClientAssets\Models\EquipmentQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class ClientAssetsEamIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client1;

    private Client $client2;

    private BpAddress $addressClient1;

    private BpAddress $addressClient2;

    private EquipmentClass $eqClass;

    private EquipmentBlueprint $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        // Clients
        $this->client1 = Client::first() ?: Client::create([
            'legal_name' => 'Alpha Industries Corp',
            'trade_name' => 'Alpha Corp',
            'status' => 'active',
        ]);

        $this->client2 = Client::where('id', '!=', $this->client1->id)->first() ?: Client::create([
            'legal_name' => 'Beta Logistics SARL',
            'trade_name' => 'Beta Logistics',
            'status' => 'active',
        ]);

        // Addresses
        $this->addressClient1 = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->client1->id,
            'type' => 'operational_site',
            'address_street' => ['en' => '10 Industrial Avenue'],
            'city' => ['en' => 'Tunis'],
            'country' => 'Tunisia',
        ]);

        $this->addressClient2 = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->client2->id,
            'type' => 'operational_site',
            'address_street' => ['en' => '20 Port Boulevard'],
            'city' => ['en' => 'Radès'],
            'country' => 'Tunisia',
        ]);

        // Equipment Class
        $this->eqClass = EquipmentClass::firstOrCreate(
            ['code' => 'test_portable_extinguisher'],
            [
                'name' => ['en' => 'Test Portable Extinguisher', 'fr' => 'Extincteur Test'],
                'description' => ['en' => 'Test class description'],
                'status' => 'active',
                'sort_order' => 1,
            ]
        );

        // Blueprint
        $this->blueprint = EquipmentBlueprint::create([
            'code' => 'BP-TEST-'.uniqid(),
            'name' => ['en' => 'Test Extinguisher Blueprint ABC 6KG'],
            'description' => ['en' => 'High performance test blueprint'],
            'status' => 'active',
            'equipment_class_id' => $this->eqClass->id,
        ]);

        // Blueprint Options
        $opt = BlueprintOption::create([
            'blueprint_id' => $this->blueprint->id,
            'name' => ['en' => 'Capacity'],
            'sort_order' => 1,
        ]);
        BlueprintOptionValue::create([
            'option_id' => $opt->id,
            'value' => ['en' => '6 kg'],
            'sort_order' => 1,
        ]);

        // Blueprint Cycles
        BlueprintInterventionCycle::create([
            'blueprint_id' => $this->blueprint->id,
            'intervention_type' => 'inspection',
            'first_interval_months' => 12,
            'recurring_interval_months' => 12,
            'notes' => 'Annual visual check',
        ]);
    }

    /** @test */
    public function it_can_fetch_equipment_classes_and_site_equipment_kpis(): void
    {
        $res = $this->getJson('/api/client-assets/equipment-classes');
        $res->assertOk()
            ->assertJsonStructure(['data', 'total']);

        $resSitesMeta = $this->getJson('/api/client-assets/sites/meta');
        $resSitesMeta->assertOk()
            ->assertJsonStructure(['total_sites', 'active_sites', 'with_areas', 'with_equipment']);

        $resEqMeta = $this->getJson('/api/client-assets/equipment/meta');
        $resEqMeta->assertOk()
            ->assertJsonStructure(['total_equipment', 'operational_count', 'maintenance_count', 'overdue_count']);
    }

    /** @test */
    public function it_creates_site_with_valid_client_address_and_rejects_mismatched_address(): void
    {
        // 1. Success case: address belongs to client1
        $res = $this->postJson('/api/client-assets/sites', [
            'client_id' => $this->client1->id,
            'bp_address_id' => $this->addressClient1->id,
            'name' => 'Alpha Campus North',
            'site_type' => 'office',
            'status' => 'active',
            'phone' => '+216 71 000 111',
            'operating_hours' => '08:00 - 17:00',
            'access_instructions' => 'Badge required',
            'safety_instructions' => 'Emergency exit on right',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.name', 'Alpha Campus North')
            ->assertJsonPath('data.client_id', $this->client1->id);

        $siteId = $res->json('data.id');

        // 2. Reject mismatched client address
        $invalidRes = $this->postJson('/api/client-assets/sites', [
            'client_id' => $this->client1->id,
            'bp_address_id' => $this->addressClient2->id, // Belongs to Client 2!
            'name' => 'Invalid Site',
            'site_type' => 'office',
        ]);

        $invalidRes->assertStatus(422)
            ->assertJsonValidationErrors(['bp_address_id']);
    }

    /** @test */
    public function it_supports_recursive_area_hierarchy_crud(): void
    {
        $site = ClientSite::create([
            'client_id' => $this->client1->id,
            'bp_address_id' => $this->addressClient1->id,
            'name' => 'Hierarchical Test Site',
            'site_type' => 'factory',
            'status' => 'active',
        ]);

        // Level 1: Building
        $resBldg = $this->postJson("/api/client-assets/sites/{$site->id}/areas", [
            'name' => 'Building Alpha',
            'area_type' => 'building',
            'code' => 'BLDG-A',
        ]);
        $resBldg->assertCreated();
        $bldgId = $resBldg->json('data.id');

        // Level 2: Floor (child of Building)
        $resFloor = $this->postJson("/api/client-assets/sites/{$site->id}/areas", [
            'parent_area_id' => $bldgId,
            'name' => 'Floor 1',
            'area_type' => 'floor',
            'code' => 'FL-1',
        ]);
        $resFloor->assertCreated();
        $floorId = $resFloor->json('data.id');

        // Level 3: Zone (child of Floor)
        $resZone = $this->postJson("/api/client-assets/sites/{$site->id}/areas", [
            'parent_area_id' => $floorId,
            'name' => 'Zone A - IT Room',
            'area_type' => 'room',
            'code' => 'IT-01',
        ]);
        $resZone->assertCreated();
        $zoneId = $resZone->json('data.id');

        // Verify hierarchy tree listing
        $resTree = $this->getJson("/api/client-assets/sites/{$site->id}/areas");
        $resTree->assertOk()
            ->assertJsonPath('data.0.name', 'Building Alpha')
            ->assertJsonPath('data.0.all_children.0.name', 'Floor 1')
            ->assertJsonPath('data.0.all_children.0.all_children.0.name', 'Zone A - IT Room');

        // Hierarchy path test
        $zoneArea = ClientSiteArea::find($zoneId);
        $this->assertEquals('Building Alpha > Floor 1 > Zone A - IT Room', $zoneArea->hierarchy_path);
    }

    /** @test */
    public function it_creates_equipment_across_all_valid_location_states(): void
    {
        $site = ClientSite::create([
            'client_id' => $this->client1->id,
            'bp_address_id' => $this->addressClient1->id,
            'name' => 'Equipment Test Site',
            'site_type' => 'office',
            'status' => 'active',
        ]);

        $area = ClientSiteArea::create([
            'site_id' => $site->id,
            'name' => 'Main Office',
            'area_type' => 'room',
        ]);

        // 1. Equipment with No Location (client_id only)
        $resNoLoc = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client1->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
            'brand' => 'Gloria',
            'model_name' => 'Extinguisher 6KG',
            'serial_number' => 'SN-NOLOC-01',
            'lifecycle_status' => 'stored',
        ]);
        $resNoLoc->assertCreated()
            ->assertJsonPath('data.site_id', null)
            ->assertJsonPath('data.area_id', null);

        // 2. Equipment with Site only (area is null)
        $resSiteOnly = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client1->id,
            'site_id' => $site->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
            'brand' => 'Gloria',
            'model_name' => 'Extinguisher 6KG',
            'serial_number' => 'SN-SITEONLY-02',
            'lifecycle_status' => 'operational',
        ]);
        $resSiteOnly->assertCreated()
            ->assertJsonPath('data.site_id', $site->id)
            ->assertJsonPath('data.area_id', null);

        // 3. Equipment with Site + Area
        $resSiteArea = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client1->id,
            'site_id' => $site->id,
            'area_id' => $area->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
            'brand' => 'Gloria',
            'model_name' => 'Extinguisher 6KG',
            'serial_number' => 'SN-SITEAREA-03',
            'lifecycle_status' => 'operational',
        ]);
        $resSiteArea->assertCreated()
            ->assertJsonPath('data.site_id', $site->id)
            ->assertJsonPath('data.area_id', $area->id);

        $eqId = $resSiteArea->json('data.id');

        // Check that initial QR, initial status history, and initial location history were atomically created
        $this->assertDatabaseHas('equipment_qr_codes', [
            'equipment_id' => $eqId,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $eqId,
            'status' => 'operational',
        ]);

        $this->assertDatabaseHas('equipment_location_histories', [
            'equipment_id' => $eqId,
            'site_id' => $site->id,
            'area_id' => $area->id,
        ]);
    }

    /** @test */
    public function it_rejects_invalid_location_hierarchies(): void
    {
        $site1 = ClientSite::create([
            'client_id' => $this->client1->id,
            'bp_address_id' => $this->addressClient1->id,
            'name' => 'Client 1 Site',
            'site_type' => 'office',
        ]);

        $site2 = ClientSite::create([
            'client_id' => $this->client2->id,
            'bp_address_id' => $this->addressClient2->id,
            'name' => 'Client 2 Site',
            'site_type' => 'office',
        ]);

        $areaSite2 = ClientSiteArea::create([
            'site_id' => $site2->id,
            'name' => 'Area on Site 2',
            'area_type' => 'room',
        ]);

        // 1. Equipment client differs from site client
        $resDiffClient = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client1->id,
            'site_id' => $site2->id, // belongs to client2!
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
        ]);
        $resDiffClient->assertStatus(422)
            ->assertJsonValidationErrors(['site_id']);

        // 2. Area belongs to different site
        $resDiffArea = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client1->id,
            'site_id' => $site1->id,
            'area_id' => $areaSite2->id, // belongs to site 2!
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
        ]);
        $resDiffArea->assertStatus(422)
            ->assertJsonValidationErrors(['area_id']);
    }

    /** @test */
    public function it_performs_atomic_equipment_transfer_and_location_history_tracking(): void
    {
        $siteA = ClientSite::create([
            'client_id' => $this->client1->id,
            'bp_address_id' => $this->addressClient1->id,
            'name' => 'Source Site A',
            'site_type' => 'office',
        ]);
        $areaA = ClientSiteArea::create(['site_id' => $siteA->id, 'name' => 'Lobby A', 'area_type' => 'zone']);

        $siteB = ClientSite::create([
            'client_id' => $this->client1->id,
            'bp_address_id' => $this->addressClient1->id,
            'name' => 'Destination Site B',
            'site_type' => 'warehouse',
        ]);
        $areaB = ClientSiteArea::create(['site_id' => $siteB->id, 'name' => 'Storage Bay B', 'area_type' => 'zone']);

        // Create equipment at Site A, Area A
        $res = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client1->id,
            'site_id' => $siteA->id,
            'area_id' => $areaA->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
            'brand' => 'Gloria',
            'model_name' => 'Extinguisher 6KG',
            'serial_number' => 'SN-TRANSFER-01',
            'lifecycle_status' => 'operational',
        ]);
        $res->assertCreated();
        $eqId = $res->json('data.id');

        // Transfer from Site A / Area A -> Site B / Area B
        $resTransfer = $this->postJson("/api/client-assets/equipment/{$eqId}/transfer", [
            'to_site_id' => $siteB->id,
            'to_area_id' => $areaB->id,
            'movement_type' => 'transfer',
            'reason' => 'Relocation to warehouse protection',
            'condition_at_transfer' => 'good',
            'notes' => 'Transferred via company van',
        ]);

        $resTransfer->assertOk()
            ->assertJsonPath('data.site_id', $siteB->id)
            ->assertJsonPath('data.area_id', $areaB->id);

        // Assert that old location history was closed (ended_at is not null)
        $this->assertDatabaseHas('equipment_location_histories', [
            'equipment_id' => $eqId,
            'site_id' => $siteA->id,
            'area_id' => $areaA->id,
        ]);
        $oldHistory = EquipmentLocationHistory::where('equipment_id', $eqId)
            ->where('site_id', $siteA->id)
            ->first();
        $this->assertNotNull($oldHistory->ended_at);

        // Assert that new location history is active (ended_at is null)
        $newHistory = EquipmentLocationHistory::where('equipment_id', $eqId)
            ->where('site_id', $siteB->id)
            ->whereNull('ended_at')
            ->first();
        $this->assertNotNull($newHistory);
        $this->assertEquals('Relocation to warehouse protection', $newHistory->reason);
    }

    /** @test */
    public function it_replaces_qr_code_atomically_and_preserves_scan_lookup(): void
    {
        $res = $this->postJson('/api/client-assets/equipment', [
            'client_id' => $this->client1->id,
            'equipment_class_id' => $this->eqClass->id,
            'blueprint_id' => $this->blueprint->id,
            'brand' => 'Gloria',
            'model_name' => 'Extinguisher 6KG',
            'serial_number' => 'SN-QR-TEST-01',
            'qr_code' => 'ORIGINAL-QR-CODE-123',
            'lifecycle_status' => 'operational',
        ]);
        $res->assertCreated();
        $eqId = $res->json('data.id');

        // Replace QR code
        $resReplace = $this->postJson("/api/client-assets/equipment/{$eqId}/replace-qr", [
            'new_qr_code' => 'NEW-ENGRAVED-QR-456',
            'reason' => 'Label damaged by rain',
            'notes' => 'Installed stainless steel metal barcode',
        ]);

        $resReplace->assertOk()
            ->assertJsonPath('data.current_qr_code.qr_code', 'NEW-ENGRAVED-QR-456');

        // Old QR is unassigned
        $oldQr = EquipmentQrCode::where('qr_code', 'ORIGINAL-QR-CODE-123')->first();
        $this->assertFalse($oldQr->is_current);
        $this->assertNotNull($oldQr->unassigned_at);

        // New QR is active
        $newQr = EquipmentQrCode::where('qr_code', 'NEW-ENGRAVED-QR-456')->first();
        $this->assertTrue($newQr->is_current);
        $this->assertNull($newQr->unassigned_at);

        // Scanning old QR still finds the equipment
        $scanOld = $this->getJson('/api/ca/scan/eq/ORIGINAL-QR-CODE-123');
        $scanOld->assertOk()
            ->assertJsonPath('data.equipment_code', $res->json('data.equipment_code'))
            ->assertJsonPath('data.is_current_qr', false);

        // Scanning new QR finds equipment as current
        $scanNew = $this->getJson('/api/ca/scan/eq/NEW-ENGRAVED-QR-456');
        $scanNew->assertOk()
            ->assertJsonPath('data.equipment_code', $res->json('data.equipment_code'))
            ->assertJsonPath('data.is_current_qr', true);
    }

    /** @test */
    public function it_filters_sites_by_risk_class_correctly(): void
    {
        $client = Client::create(['legal_name' => 'Risk Test Client', 'status' => 'active']);

        ClientSite::create([
            'client_id' => $client->id,
            'site_code' => 'CS-RISK-HIGH',
            'name' => 'High Risk Chemical Plant',
            'risk_class' => 'high',
            'status' => 'active',
        ]);

        ClientSite::create([
            'client_id' => $client->id,
            'site_code' => 'CS-RISK-LOW',
            'name' => 'Low Risk Warehouse',
            'risk_class' => 'low',
            'status' => 'active',
        ]);

        $resHigh = $this->getJson('/api/client-assets/sites?filter[risk_class]=high');
        $resHigh->assertOk();
        $this->assertCount(1, $resHigh->json('data'));
        $this->assertEquals('CS-RISK-HIGH', $resHigh->json('data.0.site_code'));

        $resLow = $this->getJson('/api/client-assets/sites?filter[risk_class]=low');
        $resLow->assertOk();
        $this->assertCount(1, $resLow->json('data'));
        $this->assertEquals('CS-RISK-LOW', $resLow->json('data.0.site_code'));
    }

    /** @test */
    public function it_filters_equipment_by_recursive_descendant_areas(): void
    {
        Sanctum::actingAs($this->user);

        $client = Client::create(['legal_name' => 'Hierarchy Client', 'status' => 'active']);
        $site = ClientSite::create([
            'client_id' => $client->id,
            'site_code' => 'CS-HIERARCHY',
            'name' => 'Hierarchy Facility',
            'status' => 'active',
        ]);

        // Zone -> Floor -> Room
        $zone = ClientSiteArea::create(['site_id' => $site->id, 'name' => 'Zone Production', 'parent_area_id' => null]);
        $floor = ClientSiteArea::create(['site_id' => $site->id, 'name' => 'Floor 1', 'parent_area_id' => $zone->id]);
        $room = ClientSiteArea::create(['site_id' => $site->id, 'name' => 'Room 101', 'parent_area_id' => $floor->id]);

        // Create equipment in nested Room 101
        $eq = ClientEquipment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'client_id' => $client->id,
            'site_id' => $site->id,
            'area_id' => $room->id,
            'equipment_code' => 'EQ-ROOM-101',
            'lifecycle_status' => 'operational',
        ]);

        // Direct filter on Zone without include_sub_areas should return 0
        $resDirect = $this->getJson("/api/client-assets/equipment?filter[site_id]={$site->id}&filter[area_id]={$zone->id}&include_sub_areas=0");
        $resDirect->assertOk();
        $this->assertCount(0, $resDirect->json('data'));

        // Filter on Zone with include_sub_areas=1 should return equipment in nested Room
        $resRecursive = $this->getJson("/api/client-assets/equipment?filter[site_id]={$site->id}&filter[area_id]={$zone->id}&include_sub_areas=1");
        $resRecursive->assertOk();
        $this->assertCount(1, $resRecursive->json('data'));
        $this->assertEquals('EQ-ROOM-101', $resRecursive->json('data.0.equipment_code'));
    }
}
