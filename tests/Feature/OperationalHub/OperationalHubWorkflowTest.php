<?php

declare(strict_types=1);

namespace Tests\Feature\OperationalHub;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Services\SalesOrderService;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\WorkTeam;
use App\Modules\OperationalHub\Enums\MissionStatus;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class OperationalHubWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private UnitOfMeasure $uom;
    private CatalogCategory $category;
    private CatalogItem $serviceItem;
    private CatalogItem $partsItem;
    private ClientSite $site;
    private ClientEquipment $equipment;
    private WorkshopUnit $workshopUnit;

    protected function setUp(): void
    {
        parent::setUp();

        // Super Admin setup
        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->uom = UnitOfMeasure::create([
            'code' => 'EA',
            'name' => 'Each',
            'symbol' => 'ea',
            'type' => 'count',
        ]);

        $this->category = CatalogCategory::create([
            'code' => 'SRV',
            'name' => ['en' => 'Services'],
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'legal_name' => 'Acme Industrial Corp',
            'commercial_name' => 'Acme Corp',
            'status' => 'active',
            'client_type' => 'corporate',
            'tax_number' => '1234567A',
            'currency' => 'TND',
            'payment_terms' => 'immediate',
        ]);

        $this->serviceItem = CatalogItem::create([
            'code' => 'SRV-001',
            'name' => ['en' => 'Annual Valve Maintenance'],
            'item_type' => 'eq_servicing',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'category_id' => $this->category->id,
            'base_price' => 750.0,
            'is_stockable' => false,
            'is_sellable' => true,
        ]);

        $this->partsItem = CatalogItem::create([
            'code' => 'PRT-001',
            'name' => ['en' => 'Replacement Seal Ring'],
            'item_type' => 'product',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'category_id' => $this->category->id,
            'base_price' => 45.0,
            'is_stockable' => true,
            'is_sellable' => true,
        ]);

        $this->site = ClientSite::create([
            'client_id' => $this->client->id,
            'name' => 'Acme Plant 1',
            'address' => 'Route de Gabes Km 4, Sfax',
            'city' => 'Sfax',
            'country' => 'Tunisia',
            'is_active' => true,
        ]);

        $blueprint = EquipmentBlueprint::create([
            'code' => 'BLU-VALVE-01',
            'name' => ['en' => 'High Pressure Valve Blueprint'],
            'status' => 'active',
        ]);

        $this->equipment = ClientEquipment::create([
            'client_id' => $this->client->id,
            'site_id' => $this->site->id,
            'blueprint_id' => $blueprint->id,
            'equipment_code' => 'EQ-VLV-2026-001',
            'serial_number' => 'SN-99882211',
            'status' => 'active',
        ]);

        $this->workshopUnit = WorkshopUnit::create([
            'code' => 'WKS-MAIN-01',
            'name' => ['en' => 'Central Mechanical Workshop'],
            'type' => 'fixed',
            'status' => 'active',
            'location' => 'Zone Industrielle Charguia',
        ]);
    }

    private function createAcceptedServiceSo(): SalesOrder
    {
        $so = app(SalesOrderService::class)->createSalesOrder([
            'so_type' => 'service',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'service_site_id' => $this->site->id,
            'payment_terms' => 'immediate',
            'currency' => 'TND',
            'lines' => [
                [
                    'catalog_item_id' => $this->serviceItem->id,
                    'qty' => 2.0,
                    'unit_price' => 750.0,
                    'item_type_snapshot' => 'service',
                ],
            ],
        ]);

        app(SalesOrderService::class)->submitForReview($so);
        app(SalesOrderService::class)->startFeasibilityReview($so);
        app(SalesOrderService::class)->reviewFeasibility($so, ['decision' => 'approved'], $this->user->id);
        app(SalesOrderService::class)->acceptSalesOrder($so, $this->user->id);

        return $so->fresh();
    }

    public function test_can_query_eligible_service_lines_and_create_swo_from_sales_order(): void
    {
        $so = $this->createAcceptedServiceSo();

        // 1. Query eligible lines
        $linesRes = $this->getJson("/api/operational-hub/swos/eligible-so-lines/{$so->id}");
        $linesRes->assertOk();
        $this->assertCount(1, $linesRes->json('data'));
        $this->assertEquals(2.0, $linesRes->json('data.0.qty_remaining'));

        // 2. Create SWO from SO
        $payload = [
            'sales_order_id'   => $so->id,
            'fulfillment_mode' => 'field_service',
            'service_site_id'  => $this->site->id,
            'priority'         => 'urgent',
            'planned_date'     => now()->addDays(2)->toDateString(),
            'notes'            => 'Urgent valve maintenance inspection',
        ];

        $res = $this->postJson('/api/operational-hub/swos/from-so', $payload);
        $res->assertCreated();
        $swoId = $res->json('data.id');
        $swoNumber = $res->json('data.swo_number');

        $this->assertStringStartsWith('SWO-', $swoNumber);
        $this->assertDatabaseHas('service_work_orders', [
            'id'             => $swoId,
            'sales_order_id' => $so->id,
            'client_id'      => $this->client->id,
            'status'         => SwoStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('swo_items', [
            'swo_id'      => $swoId,
            'qty_ordered' => 2.0,
        ]);
    }

    public function test_swo_lifecycle_transitions(): void
    {
        $so = $this->createAcceptedServiceSo();
        $soLine = $so->lines->first();

        $createRes = $this->postJson('/api/operational-hub/swos/from-so', [
            'sales_order_id' => $so->id,
            'lines' => [
                [
                    'so_line_id' => $soLine->id,
                    'qty' => (float) $soLine->qty,
                ],
            ],
        ]);
        $swoId = $createRes->json('data.id');

        // Transition: Draft -> Planned
        $transRes = $this->patchJson("/api/operational-hub/swos/{$swoId}/transition", [
            'status' => SwoStatus::Planned->value,
            'notes'  => 'Assigned to team',
        ]);
        $transRes->assertOk();
        $this->assertEquals(SwoStatus::Planned->value, $transRes->json('data.status.value'));

        // Transition: Planned -> Scheduled
        $transRes = $this->patchJson("/api/operational-hub/swos/{$swoId}/transition", [
            'status' => SwoStatus::Scheduled->value,
        ]);
        $transRes->assertOk();

        // Transition: Scheduled -> InProgress
        $transRes = $this->patchJson("/api/operational-hub/swos/{$swoId}/transition", [
            'status' => SwoStatus::InProgress->value,
        ]);
        $transRes->assertOk();
        $this->assertNotNull($transRes->json('data.started_at'));

        // Transition: InProgress -> Completed
        $transRes = $this->patchJson("/api/operational-hub/swos/{$swoId}/transition", [
            'status' => SwoStatus::Completed->value,
        ]);
        $transRes->assertOk();
        $this->assertNotNull($transRes->json('data.completed_at'));

        // Verify parent SO status auto-fulfills
        $so = $so->fresh();
        $this->assertEquals(SalesOrderStatus::Completed, $so->status);
    }

    public function test_mission_execution_with_attendance_checklist_and_interventions(): void
    {
        $so = $this->createAcceptedServiceSo();
        $swo = app(\App\Modules\OperationalHub\Services\SwoService::class)->createFromSalesOrder($so, [], $this->user);

        // 1. Create Mission under SWO
        $missionRes = $this->postJson('/api/operational-hub/missions', [
            'swo_id'       => $swo->id,
            'planned_date' => now()->toDateString(),
            'preparation_notes' => 'Bring protective gear and calibration kit',
        ]);
        $missionRes->assertCreated();
        $missionId = $missionRes->json('data.id');
        $missionNumber = $missionRes->json('data.mission_number');
        $this->assertStringStartsWith('MSN-', $missionNumber);

        // 2. Start Mission
        $startRes = $this->patchJson("/api/operational-hub/missions/{$missionId}/transition", [
            'status' => MissionStatus::Started->value,
        ]);
        $startRes->assertOk();
        $this->assertEquals(MissionStatus::Started->value, $startRes->json('data.status.value'));

        // 3. Save Checklist
        $chkRes = $this->postJson("/api/operational-hub/missions/{$missionId}/checklist", [
            'title' => 'Safety & Pressure Checklist',
            'items' => [
                ['title' => 'Site Lockout-Tagout verified', 'answer' => 'yes', 'is_completed' => true],
                ['title' => 'Personal protective gear inspected', 'answer' => 'yes', 'is_completed' => true],
            ],
        ]);
        $chkRes->assertOk();
        $this->assertDatabaseHas('mission_checklist_items', [
            'title'  => 'Site Lockout-Tagout verified',
            'answer' => 'yes',
        ]);

        // 4. Create Intervention
        $invRes = $this->postJson("/api/operational-hub/missions/{$missionId}/interventions", [
            'equipment_id'     => $this->equipment->id,
            'service_type'     => 'valve_overhaul',
            'technician_notes' => 'Replaced worn gasket seal, pressure tested to 40 bar',
            'result'           => 'pass',
            'materials'        => [
                [
                    'catalog_item_id' => $this->partsItem->id,
                    'qty_used'        => 2,
                ],
            ],
            'findings'         => [
                [
                    'type'     => 'observation',
                    'title'    => 'Minor surface oxidation on flange',
                    'severity' => 'low',
                ],
            ],
        ]);
        $invRes->assertCreated();
        $this->assertDatabaseHas('interventions', [
            'mission_id'   => $missionId,
            'equipment_id' => $this->equipment->id,
            'result'       => 'pass',
        ]);
        $this->assertDatabaseHas('intervention_materials', [
            'catalog_item_id' => $this->partsItem->id,
            'qty_used'        => 2.0,
        ]);
        $this->assertDatabaseHas('findings', [
            'title' => 'Minor surface oxidation on flange',
        ]);

        // 5. Complete Mission
        $compRes = $this->patchJson("/api/operational-hub/missions/{$missionId}/transition", [
            'status'              => MissionStatus::Completed->value,
            'client_signature'    => 'data:image/png;base64,sample-signature',
            'client_satisfaction' => 'very_satisfied',
            'completion_notes'    => 'Mission completed successfully on site',
        ]);
        $compRes->assertOk();
        $this->assertEquals(MissionStatus::Completed->value, $compRes->json('data.status.value'));
    }

    public function test_workshop_transfer_and_reception_flow(): void
    {
        $so = $this->createAcceptedServiceSo();
        $swo = app(\App\Modules\OperationalHub\Services\SwoService::class)->createFromSalesOrder($so, [], $this->user);
        $mission = app(\App\Modules\OperationalHub\Services\MissionService::class)->createMission($swo, [], $this->user);

        // Initiate transfer from field mission to workshop
        $transferRes = $this->postJson("/api/operational-hub/missions/{$mission->id}/workshop-transfer", [
            'workshop_unit_id' => $this->workshopUnit->id,
            'equipment_id'     => $this->equipment->id,
            'condition'        => 'damaged',
            'visible_damages'  => 'Internal leak in main valve body requiring workshop milling',
            'notes'            => 'Transport by dedicated service van',
        ]);
        $transferRes->assertCreated();

        $workshopSwoId = $transferRes->json('data.workshop_swo.id');
        $this->assertDatabaseHas('service_work_orders', [
            'id'                => $workshopSwoId,
            'fulfillment_mode'  => SwoFulfillmentMode::Workshop->value,
            'workshop_unit_id'  => $this->workshopUnit->id,
            'source_swo_id'     => $swo->id,
            'source_mission_id' => $mission->id,
            'status'            => SwoStatus::Draft->value,
        ]);

        // The exit pass marks departure from the field; it does NOT receive the equipment.
        $this->assertDatabaseMissing('workshop_receptions', [
            'equipment_id' => $this->equipment->id,
        ]);

        // Workshop reception is a separate physical-arrival event.
        $receptionRes = $this->postJson('/api/operational-hub/workshop-receptions', [
            'workshop_unit_id'  => $this->workshopUnit->id,
            'equipment_id'      => $this->equipment->id,
            'swo_id'            => $workshopSwoId,
            'source_mission_id' => $mission->id,
            'condition'         => 'damaged',
            'visible_damages'   => 'Internal leak in main valve body requiring workshop milling',
        ]);
        $receptionRes->assertCreated();

        $this->assertDatabaseHas('workshop_receptions', [
            'workshop_unit_id'  => $this->workshopUnit->id,
            'equipment_id'      => $this->equipment->id,
            'source_mission_id' => $mission->id,
            'condition'         => 'damaged',
        ]);
    }

    public function test_can_generate_and_sign_service_delivery_note(): void
    {
        $so = $this->createAcceptedServiceSo();
        $swo = app(\App\Modules\OperationalHub\Services\SwoService::class)->createFromSalesOrder($so, [], $this->user);
        $mission = app(\App\Modules\OperationalHub\Services\MissionService::class)->createMission($swo, [], $this->user);

        // SDNs document completed interventions (the execution source of truth).
        app(\App\Modules\OperationalHub\Services\InterventionService::class)->createIntervention($mission, [
            'equipment_id' => $this->equipment->id,
            'service_type' => 'valve_overhaul',
            'status'       => 'completed',
            'result'       => 'pass',
        ], $this->user);

        // Generate SDN
        $sdnRes = $this->postJson("/api/operational-hub/swos/{$swo->id}/delivery-note");
        $sdnRes->assertCreated();
        $sdnId = $sdnRes->json('data.id');
        $sdnNumber = $sdnRes->json('data.sdn_number');
        $this->assertStringStartsWith('SDN-', $sdnNumber);

        // Sign SDN
        $signRes = $this->patchJson("/api/operational-hub/delivery-notes/{$sdnId}/sign", [
            'client_signer_name' => 'Foued Ben Salem',
            'client_signature'   => 'data:image/svg+xml;base64,signature',
            'notes'              => 'All maintenance tasks accepted without reservations',
        ]);
        $signRes->assertOk();
        $this->assertEquals('signed', $signRes->json('data.status'));
        $this->assertEquals('Foued Ben Salem', $signRes->json('data.client_signer_name'));
    }

    public function test_dashboards_and_calendar_feed(): void
    {
        $so = $this->createAcceptedServiceSo();
        $swo = app(\App\Modules\OperationalHub\Services\SwoService::class)->createFromSalesOrder($so, [
            'planned_date' => now()->addDay()->toDateString(),
        ], $this->user);
        app(\App\Modules\OperationalHub\Services\MissionService::class)->createMission($swo, [
            'planned_date' => now()->addDay()->toDateString(),
        ], $this->user);

        // 1. Hub Dashboard
        $hubDash = $this->getJson('/api/operational-hub/dashboard');
        $hubDash->assertOk();
        $this->assertArrayHasKey('stats', $hubDash->json('data'));
        $this->assertArrayHasKey('upcoming_missions', $hubDash->json('data'));

        // 2. Workshop Dashboard
        $wksDash = $this->getJson('/api/operational-hub/workshop-dashboard');
        $wksDash->assertOk();
        $this->assertArrayHasKey('stats', $wksDash->json('data'));

        // 3. Calendar Feed
        $calFeed = $this->getJson('/api/operational-hub/planning/calendar');
        $calFeed->assertOk();
        $this->assertNotEmpty($calFeed->json());
    }
}
