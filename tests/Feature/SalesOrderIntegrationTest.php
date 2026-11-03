<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\ContractStatus;
use App\Modules\Crm\Enums\ContractType;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoCreationPath;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\GcsDocument;
use App\Modules\Crm\Models\LsaDocument;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\ServiceWorkOrder;
use App\Modules\Crm\Services\SalesOrderService;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Logistics\Enums\DwoLineStatus;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class SalesOrderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private UnitOfMeasure $uom;

    private CatalogCategory $category;

    private WarehouseLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Acme Corporation SARL',
            'commercial_name' => 'Acme Corp',
            'client_id' => 'CL-ACME-01',
            'status' => 'active',
        ]);

        $this->uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece', 'fr' => 'Pièce'],
            'type' => 'count',
        ]);

        $this->category = CatalogCategory::create([
            'name' => 'Pumps & Motors',
            'code' => 'CAT-PUMP',
        ]);

        $site = WarehouseSite::create([
            'name' => ['en' => 'Main Warehouse'],
            'code' => 'WH-MAIN',
        ]);
        $zone = WarehouseZone::create([
            'site_id' => $site->id,
            'name' => ['en' => 'Zone A'],
            'code' => 'Z-A',
        ]);
        $this->location = WarehouseLocation::create([
            'zone_id' => $zone->id,
            'barcode' => 'LOC-A01-'.rand(100, 999),
        ]);
    }

    private function createGoodsItem(string $name, float $price = 100.0): CatalogItem
    {
        $code = 'PROD-'.rand(10000, 99999);
        $item = CatalogItem::create([
            'code' => $code,
            'name' => ['en' => $name, 'fr' => $name],
            'item_type' => 'product',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'base_price' => $price,
            'is_stockable' => true,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        CatalogVariant::create([
            'catalog_item_id' => $item->id,
            'sku' => $code.'-SKU',
            'name' => $name.' Standard',
            'price' => $price,
            'is_default' => true,
        ]);

        return $item;
    }

    private function createServiceItem(string $name, float $price = 200.0): CatalogItem
    {
        $code = 'SRV-'.rand(10000, 99999);
        $item = CatalogItem::create([
            'code' => $code,
            'name' => ['en' => $name, 'fr' => $name],
            'item_type' => 'eq_servicing',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'base_price' => $price,
            'is_stockable' => false,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        \App\Modules\Catalog\Models\CatalogEqServicing::create([
            'catalog_item_id' => $item->id,
            'eq_servicing_type' => 'maintenance_and_repair',
            'delivery_mode' => 'on_site',
        ]);

        CatalogVariant::create([
            'catalog_item_id' => $item->id,
            'sku' => $code.'-SKU',
            'name' => $name.' Service',
            'price' => $price,
            'is_default' => true,
        ]);

        return $item;
    }

    private function addStock(CatalogItem $item, float $qty): StockItem
    {
        $variant = CatalogVariant::where('catalog_item_id', $item->id)->first();

        return StockItem::create([
            'catalog_item_id' => $item->id,
            'catalog_variant_id' => $variant?->id,
            'location_id' => $this->location->id,
            'qty_on_hand' => $qty,
            'qty_reserved' => 0.0,
            'qty_available' => $qty,
            'qty_in_transit' => 0.0,
            'unit_cost' => 50.0,
            'currency' => 'TND',
            'condition' => 'new',
            'status' => 'active',
        ]);
    }

    /**
     * Scenario 1: Direct Sales Order Lifecycle (GCS + Inventory Reservation; NO DWO/SWO auto-creation, status is Accepted)
     */
    public function test_scenario_1_direct_sales_order(): void
    {
        $goodsItem = $this->createGoodsItem('Hydraulic Pump HP-50', 500.0);
        $this->addStock($goodsItem, 50.0);

        // 1. Create Direct Sales Order
        $payload = [
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'currency' => 'TND',
            'lines' => [
                [
                    'catalog_item_id' => $goodsItem->id,
                    'qty' => 10,
                    'unit_price' => 500.0,
                    'item_type_snapshot' => 'physical_product',
                ],
            ],
        ];

        $response = $this->postJson('/api/crm/sales-orders', $payload);
        $response->assertCreated();
        $soId = $response->json('data.id');
        $reference = $response->json('data.reference');

        $this->assertStringStartsWith('SO-S-', $reference);
        $this->assertEquals(SoCreationPath::Manual->value, $response->json('data.creation_path'));

        // 2. Submit for review (draft -> submitted)
        $submitRes = $this->postJson("/api/crm/sales-orders/{$soId}/submit");
        $submitRes->assertOk();
        $this->assertEquals(SalesOrderStatus::Submitted->value, $submitRes->json('data.status'));

        // 3. Start feasibility review (submitted -> under_review)
        $startReviewRes = $this->postJson("/api/crm/sales-orders/{$soId}/start-feasibility-review");
        $startReviewRes->assertOk();
        $this->assertEquals(SalesOrderStatus::UnderReview->value, $startReviewRes->json('data.status'));

        // 4. Check stock
        $stockCheck = $this->getJson("/api/crm/sales-orders/{$soId}/stock-check");
        $stockCheck->assertOk();
        $this->assertEquals('sufficient', $stockCheck->json('data.result'));

        // 5. Perform feasibility review
        $reviewRes = $this->postJson("/api/crm/sales-orders/{$soId}/feasibility-review", [
            'decision' => 'approved',
            'credit_check_result' => 'sufficient',
            'stock_check_result' => 'sufficient',
        ]);
        $reviewRes->assertOk();
        $this->assertEquals(FeasibilityStatus::Approved->value, $reviewRes->json('data.feasibility_status'));
        $this->assertEquals(SalesOrderStatus::UnderReview->value, $reviewRes->json('data.status'));

        // 6. Accept order — MUST remain in accepted status (commercial commitment)
        $acceptRes = $this->postJson("/api/crm/sales-orders/{$soId}/accept");
        $acceptRes->assertOk();
        $this->assertEquals(SalesOrderStatus::Accepted->value, $acceptRes->json('data.status'));

        // Verify GCS created
        $this->assertDatabaseHas('gcs_documents', [
            'sales_order_id' => $soId,
        ]);

        // Accepted SO is commercial only — no stock reservation / movement
        $this->assertDatabaseMissing('stock_reservations', [
            'reservable_type' => SalesOrder::class,
            'reservable_id' => $soId,
        ]);

        // CRITICAL ARCHITECTURAL BOUNDARY:
        // Downstream operational documents (DWO/SWO) must NOT be auto-created upon commercial acceptance
        $this->assertDatabaseMissing('delivery_work_orders', ['sales_order_id' => $soId]);
        $this->assertDatabaseMissing('lsa_documents', ['sales_order_id' => $soId]);
        $this->assertDatabaseMissing('service_work_orders', ['sales_order_id' => $soId]);
    }

    /**
     * Scenario 2: Direct Service Order Lifecycle (LSA; NO GCS, NO SWO/DWO auto-creation, status is Accepted)
     */
    public function test_scenario_2_direct_service_order(): void
    {
        $serviceItem = $this->createServiceItem('Annual Turbine Inspection', 1200.0);

        // 1. Create Direct Service Order
        $payload = [
            'so_type' => 'service',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'payment_terms' => 'immediate',
            'currency' => 'TND',
            'lines' => [
                [
                    'catalog_item_id' => $serviceItem->id,
                    'qty' => 2,
                    'unit_price' => 1200.0,
                    'item_type_snapshot' => 'service',
                ],
            ],
        ];

        $response = $this->postJson('/api/crm/sales-orders', $payload);
        $response->assertCreated();
        $soId = $response->json('data.id');
        $reference = $response->json('data.reference');

        $this->assertStringStartsWith('SO-V-', $reference);

        // 2. Submit & Review Feasibility
        $this->postJson("/api/crm/sales-orders/{$soId}/submit")->assertOk();
        $this->postJson("/api/crm/sales-orders/{$soId}/start-feasibility-review")->assertOk();
        $this->postJson("/api/crm/sales-orders/{$soId}/feasibility-review", [
            'decision' => 'approved',
            'capacity_check_result' => 'available',
        ])->assertOk();

        // 3. Accept order — MUST remain in accepted status (commercial commitment)
        $acceptRes = $this->postJson("/api/crm/sales-orders/{$soId}/accept");
        $acceptRes->assertOk();
        $this->assertEquals(SalesOrderStatus::Accepted->value, $acceptRes->json('data.status'));

        // Verify LSA created
        $this->assertDatabaseHas('lsa_documents', [
            'sales_order_id' => $soId,
        ]);

        // CRITICAL: NO SWO, GCS, or DWO auto-created
        $this->assertDatabaseMissing('service_work_orders', ['sales_order_id' => $soId]);
        $this->assertDatabaseMissing('gcs_documents', ['sales_order_id' => $soId]);
        $this->assertDatabaseMissing('delivery_work_orders', ['sales_order_id' => $soId]);
    }

    /**
     * Scenario 3: Quotation -> SO (Preserves exact pricing, client, lines without recalculation)
     */
    public function test_scenario_3_quotation_to_sales_order(): void
    {
        $item = $this->createGoodsItem('Electric Motor EM-20', 800.0);

        $quotation = Quotation::create([
            'client_id' => $this->client->id,
            'valid_until' => now()->addDays(30),
            'currency' => 'TND',
            'payment_terms' => 'net_30',
            'total_ht' => 750.0,
            'total_tva' => 142.5,
            'total_ttc' => 892.5,
            'status' => QuotationStatus::Accepted,
        ]);

        $quotation->lines()->create([
            'catalog_item_id' => $item->id,
            'item_type_snapshot' => ItemTypeSnapshot::PhysicalProduct,
            'qty' => 1,
            'unit_price' => 750.0,
            'unit_price_after_discount' => 750.0,
            'discount_pct' => 0,
            'total_ht' => 750.0,
            'total_tva' => 142.5,
            'total_ttc' => 892.5,
        ]);

        $res = $this->postJson("/api/crm/sales-orders/convert-quotation/{$quotation->id}");
        $res->assertCreated();

        $this->assertEquals($this->client->id, $res->json('data.client_id'));
        $this->assertEquals(SoCreationPath::Quotation->value, $res->json('data.creation_path'));
        $this->assertEquals(750.0, (float) $res->json('data.total_ht'));
        $this->assertEquals(892.5, (float) $res->json('data.total_ttc'));
        $this->assertEquals(750.0, (float) $res->json('data.lines.0.unit_price'));
    }

    /**
     * Scenario 4: MSA -> Service SO (LSA snapshots MSA service commitments)
     */
    public function test_scenario_4_msa_to_service_order(): void
    {
        $contract = Contract::create([
            'type' => ContractType::Msa,
            'client_id' => $this->client->id,
            'title' => 'Master Service Agreement 2026',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'status' => ContractStatus::Active,
            'currency' => 'TND',
            'payment_terms' => 'net_30',
            'standard_response_time_hours' => 2,
            'sla_response_hours' => 2,
            'sla_resolution_hours' => 12,
            'service_quality_standard' => 'Mission-Critical 24/7 SLA Tier 1',
        ]);

        $serviceItem = $this->createServiceItem('Emergency Pipeline Diagnostics', 2000.0);

        $res = $this->postJson("/api/crm/sales-orders/convert-contract/{$contract->id}");
        $res->assertCreated();

        $soId = $res->json('data.id');
        $this->assertEquals(SalesOrderType::Service->value, $res->json('data.so_type'));
        $this->assertEquals(SoCreationPath::ContractCallOff->value, $res->json('data.creation_path'));

        // Add line to draft call-off
        $this->putJson("/api/crm/sales-orders/{$soId}", [
            'lines' => [
                [
                    'catalog_item_id' => $serviceItem->id,
                    'qty' => 1,
                    'unit_price' => 2000.0,
                    'item_type_snapshot' => 'service',
                ],
            ],
        ])->assertOk();

        // Review & Accept
        $this->postJson("/api/crm/sales-orders/{$soId}/submit")->assertOk();
        $this->postJson("/api/crm/sales-orders/{$soId}/start-feasibility-review")->assertOk();
        $this->postJson("/api/crm/sales-orders/{$soId}/feasibility-review", [
            'decision' => 'approved',
            'capacity_check_result' => 'available',
        ])->assertOk();
        $this->postJson("/api/crm/sales-orders/{$soId}/accept")->assertOk();

        // Verify LSA snapshots MSA commitments
        $lsa = LsaDocument::where('sales_order_id', $soId)->first();
        $this->assertNotNull($lsa);
        $this->assertEquals(2, $lsa->sla_response_hours);
        $this->assertEquals(12, $lsa->sla_resolution_hours);
        $this->assertEquals('Mission-Critical 24/7 SLA Tier 1', $lsa->special_terms);
    }

    /**
     * Scenario 5: Sales SO with Partial Stock (Ordered 100, Available 60 -> Reserved 60)
     */
    public function test_scenario_5_sales_so_with_partial_stock(): void
    {
        $item = $this->createGoodsItem('Standard Valve V-10', 50.0);
        $this->addStock($item, 60.0);

        $so = app(SalesOrderService::class)->createSalesOrder([
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'lines' => [
                [
                    'catalog_item_id' => $item->id,
                    'qty' => 100.0,
                    'unit_price' => 50.0,
                    'item_type_snapshot' => 'physical_product',
                ],
            ],
        ]);

        // Stock check should return partial
        $stockCheck = $this->getJson("/api/crm/sales-orders/{$so->id}/stock-check");
        $stockCheck->assertOk();
        $this->assertEquals('partial', $stockCheck->json('data.result'));
        $this->assertEquals(60.0, (float) $stockCheck->json('data.lines.0.qty_reservable'));

        // Review and accept
        app(SalesOrderService::class)->submitForReview($so);
        app(SalesOrderService::class)->startFeasibilityReview($so);
        app(SalesOrderService::class)->reviewFeasibility($so, [
            'decision' => 'approved',
            'stock_check_result' => 'partial',
        ], $this->user->id);
        app(SalesOrderService::class)->acceptSalesOrder($so, $this->user->id);

        $this->assertSame(0, StockReservation::where('reservable_type', SalesOrder::class)
            ->where('reservable_id', $so->id)
            ->count());
    }

    /**
     * Scenario 6: Multiple DWO Fulfillment Progress (Fan-out model from accepted SO)
     */
    public function test_scenario_6_multiple_dwo_fulfillment(): void
    {
        $item = $this->createGoodsItem('Flange FL-100', 30.0);
        $this->addStock($item, 100.0);

        $so = app(SalesOrderService::class)->createSalesOrder([
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'lines' => [
                [
                    'catalog_item_id' => $item->id,
                    'qty' => 100.0,
                    'unit_price' => 30.0,
                    'item_type_snapshot' => 'physical_product',
                ],
            ],
        ]);

        app(SalesOrderService::class)->submitForReview($so);
        app(SalesOrderService::class)->startFeasibilityReview($so);
        app(SalesOrderService::class)->reviewFeasibility($so, ['decision' => 'approved'], $this->user->id);
        app(SalesOrderService::class)->acceptSalesOrder($so, $this->user->id);

        $so = $so->fresh();
        $this->assertEquals(SalesOrderStatus::Accepted, $so->status);

        $soLine = $so->lines->first();

        $dwo1 = DeliveryWorkOrder::create([
            'sales_order_id' => $so->id,
            'client_id' => $so->client_id,
            'status' => 'draft',
            'scheduled_date' => today(),
        ]);

        $dwo2 = DeliveryWorkOrder::create([
            'sales_order_id' => $so->id,
            'client_id' => $so->client_id,
            'status' => 'draft',
            'scheduled_date' => today(),
        ]);

        DwoLine::create([
            'dwo_id' => $dwo1->id,
            'so_item_id' => $soLine->id,
            'catalog_item_id' => $item->id,
            'qty_ordered' => 100,
            'qty_allocated' => 50,
            'qty_delivered' => 50,
            'item_status' => DwoLineStatus::Delivered,
        ]);

        DwoLine::create([
            'dwo_id' => $dwo2->id,
            'so_item_id' => $soLine->id,
            'catalog_item_id' => $item->id,
            'qty_ordered' => 100,
            'qty_allocated' => 50,
            'qty_delivered' => 0,
            'item_status' => DwoLineStatus::Pending,
        ]);

$dwo1->update(['status' => 'delivered']);
        app(SalesOrderService::class)->checkAndCloseSalesOrder($so);

        $so = $so->fresh();
        $this->assertEquals('in_progress', $so->calculateFulfillmentStatus());
        $this->assertEquals(SalesOrderStatus::Accepted, $so->status);

        $dwo2->lines()->update([
            'qty_delivered' => 50,
            'item_status' => DwoLineStatus::Delivered->value,
        ]);
        $dwo2->update(['status' => 'delivered']);
        app(SalesOrderService::class)->checkAndCloseSalesOrder($so->fresh());
        $so = $so->fresh();
        $this->assertEquals('complete', $so->calculateFulfillmentStatus());
        $this->assertEquals(SalesOrderStatus::Completed, $so->status);
    }

    /**
     * Scenario 7: Multiple SWO Fulfillment Progress (Fan-out model from accepted SO)
     */
    public function test_scenario_7_multiple_swo_fulfillment(): void
    {
        $service = $this->createServiceItem('Multi-phase System Calibration', 500.0);

        $so = app(SalesOrderService::class)->createSalesOrder([
            'so_type' => 'service',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'lines' => [
                [
                    'catalog_item_id' => $service->id,
                    'qty' => 3.0,
                    'unit_price' => 500.0,
                    'item_type_snapshot' => 'service',
                ],
            ],
        ]);

        app(SalesOrderService::class)->submitForReview($so);
        app(SalesOrderService::class)->startFeasibilityReview($so);
        app(SalesOrderService::class)->reviewFeasibility($so, ['decision' => 'approved'], $this->user->id);
        app(SalesOrderService::class)->acceptSalesOrder($so, $this->user->id);

        $so = $so->fresh();
        $this->assertEquals(SalesOrderStatus::Accepted, $so->status);

        // Downstream operational users create SWOs independently
        $swo1 = ServiceWorkOrder::create([
            'sales_order_id' => $so->id,
            'client_id' => $so->client_id,
            'status' => 'completed',
            'fulfillment_mode' => 'field_service',
        ]);

        // Add SWO items with completed quantities
        $swo1->items()->create([
            'so_line_id' => $so->lines->first()->id,
            'catalog_item_id' => $service->id,
            'qty_ordered' => 1.0,
            'qty_completed' => 1.0,
            'status' => 'completed',
        ]);

        $swo2 = ServiceWorkOrder::create([
            'sales_order_id' => $so->id,
            'client_id' => $so->client_id,
            'status' => 'completed',
            'fulfillment_mode' => 'field_service',
        ]);

        // Add SWO items for second SWO
        $swo2->items()->create([
            'so_line_id' => $so->lines->first()->id,
            'catalog_item_id' => $service->id,
            'qty_ordered' => 2.0,
            'qty_completed' => 0.0, // Not completed yet
            'status' => 'pending',
        ]);

        $this->assertSame(2, ServiceWorkOrder::where('sales_order_id', $so->id)->count());
        $this->assertTrue($so->fresh()->isService());

        // First SWO completed (1.0/3.0 = in_progress)
        $this->assertEquals('in_progress', $so->fresh()->calculateFulfillmentStatus());

        // Complete second SWO (2.0 more completed = 3.0 total = complete)
        $swo2->items()->update(['qty_completed' => 2.0, 'status' => 'completed']);
        $swo2->update(['status' => 'completed']);
        app(SalesOrderService::class)->checkAndCloseSalesOrder($so->fresh());
        $so = $so->fresh();
        $this->assertEquals('complete', $so->calculateFulfillmentStatus());
        $this->assertEquals(SalesOrderStatus::Completed, $so->status);
    }

    /**
     * Scenario 8: Duplicate Acceptance Event / Idempotency Test
     */
    public function test_scenario_8_duplicate_event_idempotency(): void
    {
        $item = $this->createGoodsItem('Compressor Valve', 150.0);
        $this->addStock($item, 20.0);

        $so = app(SalesOrderService::class)->createSalesOrder([
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'lines' => [
                [
                    'catalog_item_id' => $item->id,
                    'qty' => 5.0,
                    'unit_price' => 150.0,
                    'item_type_snapshot' => 'physical_product',
                ],
            ],
        ]);

        app(SalesOrderService::class)->submitForReview($so);
        app(SalesOrderService::class)->startFeasibilityReview($so);
        app(SalesOrderService::class)->reviewFeasibility($so, ['decision' => 'approved'], $this->user->id);

        // First acceptance call
        app(SalesOrderService::class)->acceptSalesOrder($so, $this->user->id);

        $gcsCountFirst = GcsDocument::where('sales_order_id', $so->id)->count();
        $resCountFirst = StockReservation::where('reservable_id', $so->id)->count();

        $this->assertEquals(1, $gcsCountFirst);
        $this->assertEquals(0, $resCountFirst);

        // Trigger acceptance SECOND TIME (Retry / duplicate event)
        app(SalesOrderService::class)->acceptSalesOrder($so, $this->user->id);

        $gcsCountSecond = GcsDocument::where('sales_order_id', $so->id)->count();
        $resCountSecond = StockReservation::where('reservable_id', $so->id)->count();

        $this->assertEquals(1, $gcsCountSecond);
        $this->assertEquals(0, $resCountSecond);
    }

    /**
     * Test Backend Type Restriction: Cannot add service item to Sales SO
     */
    public function test_backend_enforces_type_restrictions(): void
    {
        $serviceItem = $this->createServiceItem('Turbine Maintenance', 500.0);

        $response = $this->postJson('/api/crm/sales-orders', [
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'lines' => [
                [
                    'catalog_item_id' => $serviceItem->id,
                    'qty' => 1,
                    'unit_price' => 500.0,
                    'item_type_snapshot' => 'service',
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
