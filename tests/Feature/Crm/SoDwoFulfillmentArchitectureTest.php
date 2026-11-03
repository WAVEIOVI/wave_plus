<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoFulfillmentMode;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Crm\Services\SalesOrderAcceptanceService;
use App\Modules\Crm\Services\SalesOrderConversionService;
use App\Modules\Crm\Services\SalesOrderFulfillmentService;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Services\DeliveryNoteStockService;
use App\Modules\Logistics\Services\DwoCreationService;
use App\Modules\Logistics\Services\DWOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class SoDwoFulfillmentArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private BpAddress $address;
    private CatalogItem $goodsItem;
    private CatalogItem $serviceItem;
    private CatalogVariant $variant;
    private CatalogVariant $altVariant;
    private WarehouseSite $warehouse;
    private WarehouseLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Architecture Client SARL',
            'status' => 'active',
        ]);

        $this->address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->client->id,
            'type' => 'shipping',
            'label' => 'Site A',
            'address_street' => '1 Rue Test',
            'city' => 'Tunis',
            'country' => 'Tunisia',
            'is_primary' => true,
        ]);

        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCE'],
            ['name' => ['en' => 'Piece'], 'type' => 'count']
        );

        $category = CatalogCategory::create([
            'name' => ['fr' => 'Test'],
        ]);

        $this->goodsItem = CatalogItem::create([
            'code' => 'GDS-'.uniqid(),
            'name' => ['fr' => 'Goods Item'],
            'item_type' => 'product',
            'is_stockable' => true,
            'is_sellable' => true,
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'status' => 'active',
        ]);

        $this->serviceItem = CatalogItem::create([
            'code' => 'SRV-'.uniqid(),
            'name' => ['fr' => 'Service Item'],
            'item_type' => 'eq_servicing',
            'is_stockable' => false,
            'is_sellable' => true,
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'status' => 'active',
        ]);

        $this->variant = CatalogVariant::create([
            'catalog_item_id' => $this->goodsItem->id,
            'sku' => 'SKU-A-'.uniqid(),
            'name' => 'Variant A',
            'is_default' => true,
        ]);

        $this->altVariant = CatalogVariant::create([
            'catalog_item_id' => $this->goodsItem->id,
            'sku' => 'SKU-B-'.uniqid(),
            'name' => 'Variant B',
            'is_default' => false,
        ]);

        $this->warehouse = WarehouseSite::create([
            'code' => 'WH-'.uniqid(),
            'name' => 'Main WH',
            'status' => 'active',
        ]);

        $zone = WarehouseZone::create([
            'site_id' => $this->warehouse->id,
            'code' => 'Z-'.uniqid(),
            'name' => 'Zone A',
            'type' => 'standard',
        ]);

        $this->location = WarehouseLocation::create([
            'zone_id' => $zone->id,
            'barcode' => 'LOC-'.uniqid(),
            'location_type' => 'shelf',
            'is_active' => true,
        ]);
    }

    public function test_so_acceptance_does_not_reserve_or_move_stock(): void
    {
        $this->seedStock(20);

        $so = $this->makeAcceptedReadyGoodsSo(100);

        $so = app(SalesOrderAcceptanceService::class)->accept($so, $this->user->id);

        $this->assertSame(SalesOrderStatus::Accepted, $so->status);
        $this->assertSame(0, StockReservation::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, DeliveryWorkOrder::count());
    }

    public function test_dwo_allocation_reserves_only_available_stock_and_records_shortage(): void
    {
        $this->seedStock(20);

        $so = $this->makeAcceptedGoodsSo(100);

        $dwo = app(DwoCreationService::class)->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'fulfillment_method' => 'customer_pickup',
            'lines' => [
                ['so_line_id' => $so->lines->first()->id, 'qty_to_deliver' => 60],
            ],
        ], $this->user);

        $line = $dwo->lines->first();
        $this->assertEquals(60.0, (float) $line->qty_allocated);
        $this->assertEquals(20.0, (float) $line->qty_reserved);
        $this->assertEquals(40.0, (float) $line->qty_shortage);
        $this->assertSame('customer_pickup', $dwo->fulfillment_method);
        $this->assertSame(SoFulfillmentMode::OwnDelivery, $so->fresh()->requested_fulfillment_mode);
    }

    public function test_insufficient_stock_does_not_block_dwo_creation(): void
    {
        $so = $this->makeAcceptedGoodsSo(50);

        $dwo = app(DwoCreationService::class)->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'lines' => [
                ['so_line_id' => $so->lines->first()->id, 'qty_to_deliver' => 50],
            ],
        ], $this->user);

        $this->assertNotNull($dwo->id);
        $this->assertEquals(0.0, (float) $dwo->lines->first()->qty_reserved);
        $this->assertEquals(50.0, (float) $dwo->lines->first()->qty_shortage);
    }

    public function test_multiple_dwos_and_partial_delivery_do_not_auto_fulfill_so(): void
    {
        $this->seedStock(200);

        $so = $this->makeAcceptedGoodsSo(100);
        $soLineId = $so->lines->first()->id;
        $creation = app(DwoCreationService::class);

        $dwo1 = $creation->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [['so_line_id' => $soLineId, 'qty_to_deliver' => 40]],
        ], $this->user);

        $dwo2 = $creation->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [['so_line_id' => $soLineId, 'qty_to_deliver' => 30]],
        ], $this->user);

        $dwo3 = $creation->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [['so_line_id' => $soLineId, 'qty_to_deliver' => 30]],
        ], $this->user);

        $dwo1->lines->first()->update(['qty_delivered' => 40]);
        $dwo2->lines->first()->update(['qty_delivered' => 20]);
        $dwo3->lines->first()->update(['qty_delivered' => 0]);

        app(DWOService::class)->transition($dwo3, DwoStatus::Cancelled, $this->user);

        $summary = app(SalesOrderFulfillmentService::class)->getFulfillmentSummary($so->fresh());

        $this->assertEquals(100.0, $summary['ordered_qty']);
        $this->assertEquals(70.0, $summary['allocated_qty']);
        $this->assertEquals(60.0, $summary['delivered_qty']);
        $this->assertEquals(40.0, $summary['remaining_qty']);
        $this->assertFalse($summary['is_fully_fulfilled']);
        $this->assertSame(SalesOrderStatus::Accepted, $so->fresh()->status);
    }

    public function test_delivery_note_confirmation_posts_one_stock_movement(): void
    {
        $this->seedStock(50);
        $so = $this->makeAcceptedGoodsSo(10);

        $dwo = app(DwoCreationService::class)->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [['so_line_id' => $so->lines->first()->id, 'qty_to_deliver' => 10]],
        ], $this->user);

        $dwo->lines->first()->update(['qty_delivered' => 10]);

        $note = DeliveryNote::create([
            'dn_number' => 'DN-TEST-1',
            'dwo_id' => $dwo->id,
            'client_id' => $this->client->id,
            'delivery_date' => now(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $first = app(DeliveryNoteStockService::class)->confirmAndPost($note, $this->user->id);
        $second = app(DeliveryNoteStockService::class)->confirmAndPost($note->fresh(), $this->user->id);

        $this->assertNotEmpty($first);
        $this->assertCount(count($first), $second);
        $this->assertSame(1, StockMovement::where('movement_type', 'SALES_DISPATCH')->count());
        $this->assertNotNull($note->fresh()->stock_posted_at);
        $this->assertSame(0, StockMovement::where('source_document_type', DeliveryWorkOrder::class)->count());
    }

    public function test_mixed_quotation_splits_into_goods_and_service_sales_orders(): void
    {
        $quote = Quotation::create([
            'client_id' => $this->client->id,
            'valid_until' => now()->addDays(30),
            'currency' => 'TND',
            'payment_terms' => PaymentTerms::Net30,
            'status' => QuotationStatus::Accepted,
            'total_ht' => 200,
            'total_tva' => 38,
            'total_ttc' => 238,
        ]);

        $quote->lines()->create([
            'catalog_item_id' => $this->goodsItem->id,
            'variant_id' => $this->variant->id,
            'item_type_snapshot' => ItemTypeSnapshot::PhysicalProduct,
            'qty' => 2,
            'unit_price' => 50,
            'unit_price_after_discount' => 50,
            'discount_pct' => 0,
            'total_ht' => 100,
            'total_tva' => 19,
            'total_ttc' => 119,
        ]);

        $quote->lines()->create([
            'catalog_item_id' => $this->serviceItem->id,
            'item_type_snapshot' => ItemTypeSnapshot::Service,
            'qty' => 1,
            'unit_price' => 100,
            'unit_price_after_discount' => 100,
            'discount_pct' => 0,
            'total_ht' => 100,
            'total_tva' => 19,
            'total_ttc' => 119,
        ]);

        $orders = app(SalesOrderConversionService::class)->createFromQuotation($quote);

        $this->assertCount(2, $orders);
        $types = $orders->map(fn (SalesOrder $so) => $so->so_type)->all();
        $this->assertContains(SalesOrderType::Sales, $types);
        $this->assertContains(SalesOrderType::Service, $types);

        $goods = $orders->first(fn (SalesOrder $so) => $so->isSales());
        $service = $orders->first(fn (SalesOrder $so) => $so->isService());

        $this->assertCount(1, $goods->lines);
        $this->assertCount(1, $service->lines);
        $this->assertEquals(100.0, (float) $goods->total_ht);
        $this->assertEquals(100.0, (float) $service->total_ht);
        $this->assertSame($quote->id, $goods->quotation_id);
        $this->assertSame($quote->id, $service->quotation_id);
        $this->assertSame(SoFulfillmentMode::FieldService, $service->requested_fulfillment_mode);
    }

    public function test_variant_substitution_keeps_so_line_unchanged(): void
    {
        $this->seedStock(10);
        $so = $this->makeAcceptedGoodsSo(5);
        $soLine = $so->lines->first();

        $dwo = app(DwoCreationService::class)->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [[
                'so_line_id' => $soLine->id,
                'qty_to_deliver' => 5,
                'proposed_catalog_variant_id' => $this->altVariant->id,
                'substitution_reason' => 'Warehouse substitution',
            ]],
        ], $this->user);

        $dwoLine = $dwo->lines->first();
        $this->assertSame('proposed', $dwoLine->substitution_status);
        $this->assertEquals($this->variant->id, $dwoLine->commercial_catalog_variant_id);
        // Before approval: actual catalog_variant_id = commercial variant
        // Proposed variant is stored in proposed_catalog_variant_id
        $this->assertEquals($this->variant->id, $dwoLine->catalog_variant_id);
        $this->assertEquals($this->altVariant->id, $dwoLine->proposed_catalog_variant_id);
        $this->assertEquals($this->variant->id, $soLine->fresh()->variant_id);
    }

    public function test_service_so_does_not_create_dwo(): void
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'status' => SalesOrderStatus::Accepted,
            'so_type' => SalesOrderType::Service,
            'requested_fulfillment_mode' => SoFulfillmentMode::Workshop,
            'payment_terms' => PaymentTerms::Immediate,
            'currency' => 'TND',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(DwoCreationService::class)->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'lines' => [],
        ], $this->user);
    }

    private function seedStock(float $qty): void
    {
        StockItem::create([
            'catalog_item_id' => $this->goodsItem->id,
            'catalog_variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'qty_on_hand' => $qty,
            'qty_reserved' => 0,
            'qty_available' => $qty,
            'unit_cost' => 10,
            'currency' => 'TND',
            'status' => 'active',
        ]);
    }

    private function makeAcceptedGoodsSo(float $qty): SalesOrder
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'status' => SalesOrderStatus::Accepted,
            'so_type' => SalesOrderType::Sales,
            'requested_fulfillment_mode' => SoFulfillmentMode::OwnDelivery,
            'payment_terms' => PaymentTerms::Immediate,
            'currency' => 'TND',
            'total_ht' => $qty * 10,
            'total_ttc' => $qty * 11.9,
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $so->id,
            'catalog_item_id' => $this->goodsItem->id,
            'variant_id' => $this->variant->id,
            'item_type_snapshot' => ItemTypeSnapshot::PhysicalProduct,
            'qty' => $qty,
            'unit_price' => 10,
            'total_ht' => $qty * 10,
            'total_ttc' => $qty * 11.9,
        ]);

        return $so->fresh('lines');
    }

    private function makeAcceptedReadyGoodsSo(float $qty): SalesOrder
    {
        $so = $this->makeAcceptedGoodsSo($qty);
        $so->update([
            'status' => SalesOrderStatus::UnderReview,
            'feasibility_status' => FeasibilityStatus::Approved,
        ]);

        return $so->fresh('lines');
    }
}
