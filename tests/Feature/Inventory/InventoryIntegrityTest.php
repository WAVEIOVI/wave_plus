<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoCreationPath;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Inventory\Events\GrnConfirmed;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Models\GrnLine;
use App\Modules\Procurement\Models\PoLine;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Permission;
use App\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CatalogItem $item;

    private CatalogVariant $variant;

    private WarehouseLocation $location;

    private WarehouseLocation $destLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece', 'fr' => 'Pièce'],
            'type' => 'count',
        ]);
        $category = CatalogCategory::create([
            'name' => ['en' => 'Parts'],
            'is_active' => true,
        ]);
        $this->item = CatalogItem::create([
            'code' => 'INV-100',
            'name' => ['en' => 'Filter Cartridge'],
            'item_type' => 'product',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'is_stockable' => true,
            'status' => 'active',
        ]);
        $this->variant = CatalogVariant::create([
            'catalog_item_id' => $this->item->id,
            'sku' => 'INV-100-SKU',
            'name' => 'Standard',
            'price' => 25,
            'is_default' => true,
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
            'barcode' => 'LOC-A01-'.rand(1000, 9999),
            'is_active' => true,
        ]);
        $this->destLocation = WarehouseLocation::create([
            'zone_id' => $zone->id,
            'barcode' => 'LOC-B01-'.rand(1000, 9999),
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_access_inventory(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/inventory/stock-items')->assertForbidden();
        $this->getJson('/api/inventory/movements')->assertForbidden();
        $this->postJson('/api/inventory/movements/adjustment', $this->adjustmentPayload())->assertForbidden();
    }

    public function test_reader_can_list_stock_but_cannot_adjust(): void
    {
        Permission::findOrCreate('read-InventoryWarehouse', 'web');
        $reader = User::factory()->create();
        $reader->givePermissionTo(['read-InventoryWarehouse']);
        Sanctum::actingAs($reader);

        $this->getJson('/api/inventory/stock-items')->assertOk();
        $this->getJson('/api/inventory/movements')->assertOk();
        $this->postJson('/api/inventory/movements/adjustment', $this->adjustmentPayload())->assertForbidden();
    }

    public function test_initial_load_creates_ledger_and_on_hand_cache(): void
    {
        $this->postJson('/api/inventory/movements/initial-load', [
            'items' => [[
                'catalog_item_id' => $this->item->id,
                'catalog_variant_id' => $this->variant->id,
                'location_id' => $this->location->id,
                'quantity' => 10,
                'unit_cost' => 12.5,
            ]],
        ])->assertCreated();

        $this->assertSame(1, StockMovement::where('movement_type', 'INITIAL_LOAD')->count());
        $this->assertEquals('10.0000', $this->onHand());
        $this->assertEquals('10.0000', $this->available());
    }

    public function test_adjustment_minus_beyond_available_is_rejected_and_qty_unchanged(): void
    {
        $this->loadStock(5);

        $this->postJson('/api/inventory/movements/adjustment', $this->adjustmentPayload([
            'quantity' => 9,
            'adjustment_type' => 'minus',
        ]))->assertStatus(422);

        $this->assertEquals('5.0000', $this->onHand());
        $this->assertSame(0, StockMovement::where('movement_type', 'ADJUSTMENT_MINUS')->count());
    }

    public function test_transfer_moves_on_hand_and_writes_two_movements(): void
    {
        $this->loadStock(8);

        $this->postJson('/api/inventory/movements/transfer', [
            'catalog_item_id' => $this->item->id,
            'catalog_variant_id' => $this->variant->id,
            'from_location_id' => $this->location->id,
            'to_location_id' => $this->destLocation->id,
            'quantity' => 3,
            'reason' => 'Rebalance',
        ])->assertCreated();

        $this->assertEquals('5.0000', $this->onHand($this->location));
        $this->assertEquals('3.0000', $this->onHand($this->destLocation));
        $this->assertSame(1, StockMovement::where('movement_type', 'TRANSFER_OUT')->count());
        $this->assertSame(1, StockMovement::where('movement_type', 'TRANSFER_IN')->count());
    }

    public function test_failed_transfer_does_not_write_movements_or_change_qty(): void
    {
        $this->loadStock(4);

        $this->postJson('/api/inventory/movements/transfer', [
            'catalog_item_id' => $this->item->id,
            'catalog_variant_id' => $this->variant->id,
            'from_location_id' => $this->location->id,
            'to_location_id' => $this->destLocation->id,
            'quantity' => 9,
        ])->assertStatus(422);

        $this->assertEquals('4.0000', $this->onHand());
        $this->assertSame(0, StockMovement::whereIn('movement_type', ['TRANSFER_OUT', 'TRANSFER_IN'])->count());
        $this->assertNull(StockItem::where('location_id', $this->destLocation->id)->first());
    }

    public function test_sequential_full_transfers_do_not_overdraw(): void
    {
        $this->loadStock(6);

        $payload = [
            'catalog_item_id' => $this->item->id,
            'catalog_variant_id' => $this->variant->id,
            'from_location_id' => $this->location->id,
            'to_location_id' => $this->destLocation->id,
            'quantity' => 6,
        ];

        $this->postJson('/api/inventory/movements/transfer', $payload)->assertCreated();
        $this->postJson('/api/inventory/movements/transfer', $payload)->assertStatus(422);

        $this->assertEquals('0.0000', $this->onHand($this->location));
        $this->assertEquals('6.0000', $this->onHand($this->destLocation));
        $this->assertSame(1, StockMovement::where('movement_type', 'TRANSFER_OUT')->count());
    }

    public function test_inactive_location_rejects_stock_writes(): void
    {
        $this->location->update(['is_active' => false]);

        $this->postJson('/api/inventory/movements/initial-load', [
            'items' => [[
                'catalog_item_id' => $this->item->id,
                'catalog_variant_id' => $this->variant->id,
                'location_id' => $this->location->id,
                'quantity' => 2,
                'unit_cost' => 1,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, StockItem::count());
    }

    public function test_stock_items_cannot_be_written_via_http(): void
    {
        $this->loadStock(2);
        $stockItem = StockItem::firstOrFail();

        $this->putJson("/api/inventory/stock-items/{$stockItem->id}", [
            'qty_on_hand' => 999,
        ])->assertStatus(405);

        $this->postJson('/api/inventory/stock-items', [
            'qty_on_hand' => 1,
        ])->assertStatus(405);

        $this->assertEquals('2.0000', $stockItem->fresh()->qty_on_hand);
    }

    public function test_stock_movements_are_immutable(): void
    {
        $this->loadStock(1);
        $movement = StockMovement::firstOrFail();

        $this->deleteJson("/api/inventory/movements/{$movement->id}")->assertStatus(405);
        $this->putJson("/api/inventory/movements/{$movement->id}", ['reason' => 'tamper'])->assertStatus(405);

        try {
            $movement->reason = 'tamper';
            $movement->save();
            $this->fail('Expected movement update to abort.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        try {
            $movement->delete();
            $this->fail('Expected movement delete to abort.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(1, StockMovement::count());
        $this->assertSame('Initial stock load', $movement->fresh()->reason);
    }

    public function test_filter_type_alias_lists_movements(): void
    {
        $this->loadStock(1);

        $this->getJson('/api/inventory/movements?filter[type]=INITIAL_LOAD')
            ->assertOk()
            ->assertJsonPath('data.0.movement_type', 'INITIAL_LOAD');
    }

    public function test_grn_confirm_is_idempotent_and_partial_receipt_posts_accepted_qty(): void
    {
        $grn = $this->makeGrn(qtyReceived: 10, qtyAccepted: 3);
        $service = app(StockMovementService::class);

        $service->handleGrnConfirmed(new GrnConfirmed($grn, $this->user->id));
        $service->handleGrnConfirmed(new GrnConfirmed($grn->fresh(), $this->user->id));

        $this->assertSame(1, StockMovement::where('movement_type', 'GRN_RECEIPT')->count());
        $this->assertEquals('3.0000', $this->onHand());
        $this->assertSame('stocked', $grn->fresh()->status);
    }

    public function test_sales_dispatch_rejects_shortfall_and_second_call_is_idempotent(): void
    {
        $this->loadStock(4);
        $line = $this->makeSalesLine(10);
        $service = app(StockMovementService::class);

        try {
            $service->dispatchStock($line, $this->user->id);
            $this->fail('Expected dispatch shortfall to abort.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertEquals('4.0000', $this->onHand());
        $this->assertSame(0, StockMovement::where('movement_type', 'SALES_DISPATCH')->count());

        $partial = $this->makeSalesLine(3);
        $first = $service->dispatchStock($partial, $this->user->id);
        $second = $service->dispatchStock($partial->fresh(), $this->user->id);

        $this->assertCount(1, $first);
        $this->assertSame([], $second);
        $this->assertSame(1, StockMovement::where('movement_type', 'SALES_DISPATCH')->count());
        $this->assertEquals('1.0000', $this->onHand());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function adjustmentPayload(array $overrides = []): array
    {
        return array_merge([
            'catalog_item_id' => $this->item->id,
            'catalog_variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity' => 1,
            'adjustment_type' => 'plus',
            'reason' => 'Cycle count',
        ], $overrides);
    }

    private function loadStock(float $qty): void
    {
        $this->postJson('/api/inventory/movements/initial-load', [
            'items' => [[
                'catalog_item_id' => $this->item->id,
                'catalog_variant_id' => $this->variant->id,
                'location_id' => $this->location->id,
                'quantity' => $qty,
                'unit_cost' => 10,
            ]],
        ])->assertCreated();
    }

    private function onHand(?WarehouseLocation $location = null): string
    {
        $item = StockItem::query()
            ->where('catalog_item_id', $this->item->id)
            ->where('location_id', ($location ?? $this->location)->id)
            ->first();

        return $item?->qty_on_hand ?? '0.0000';
    }

    private function available(): string
    {
        return StockItem::query()
            ->where('catalog_item_id', $this->item->id)
            ->where('location_id', $this->location->id)
            ->value('qty_available') ?? '0.0000';
    }

    private function makeGrn(float $qtyReceived, float $qtyAccepted): GoodsReceiptNote
    {
        $supplier = Supplier::create([
            'legal_name' => 'Atlas Supplies',
            'supplier_category' => 'distributor',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'creation_path' => 'manual',
            'supplier_id' => $supplier->id,
            'currency' => 'TND',
            'payment_terms' => 'net_30',
            'status' => 'acknowledged',
        ]);

        $poLine = PoLine::create([
            'po_id' => $po->id,
            'catalog_item_id' => $this->item->id,
            'variant_id' => $this->variant->id,
            'description_snapshot' => 'Filter Cartridge',
            'qty_ordered' => $qtyReceived,
            'unit_price_ht' => 10,
            'tax_rate_pct' => 19,
            'total_ht' => $qtyReceived * 10,
            'total_tax' => 0,
            'total_ttc' => $qtyReceived * 10,
        ]);

        $grn = GoodsReceiptNote::create([
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by' => $this->user->id,
            'received_at' => now(),
            'status' => 'confirmed',
        ]);

        GrnLine::create([
            'grn_id' => $grn->id,
            'po_line_id' => $poLine->id,
            'catalog_item_id' => $this->item->id,
            'catalog_variant_id' => $this->variant->id,
            'qty_received' => $qtyReceived,
            'qty_accepted' => $qtyAccepted,
            'qty_rejected' => $qtyReceived - $qtyAccepted,
            'unit_cost_ht' => 10,
            'storage_location_id' => $this->location->id,
        ]);

        return $grn->fresh(['lines', 'purchaseOrder']);
    }

    private function makeSalesLine(float $qty): SalesOrderLine
    {
        $client = Client::create([
            'legal_name' => 'Acme SARL',
            'commercial_name' => 'Acme',
            'client_id' => 'CL-INV-'.rand(100, 999),
            'status' => 'active',
        ]);

        $order = SalesOrder::create([
            'so_type' => SalesOrderType::Sales,
            'creation_path' => SoCreationPath::Manual,
            'client_id' => $client->id,
            'status' => SalesOrderStatus::Accepted,
            'currency' => 'TND',
            'payment_terms' => PaymentTerms::Net30,
            'has_goods_lines' => true,
        ]);

        return SalesOrderLine::create([
            'sales_order_id' => $order->id,
            'catalog_item_id' => $this->item->id,
            'variant_id' => $this->variant->id,
            'item_type_snapshot' => ItemTypeSnapshot::PhysicalProduct,
            'qty' => $qty,
            'unit_price' => 25,
        ]);
    }
}
