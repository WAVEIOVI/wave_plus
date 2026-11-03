<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Logistics\Services\DwoCreationService;
use App\Modules\Logistics\Services\DwoPickingService;
use App\Modules\Logistics\Services\DWOService;
use App\Modules\Logistics\Services\DeliveryNoteStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class DwoStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private BpAddress $address;
    private CatalogItem $item;
    private CatalogVariant $variant;
    private WarehouseSite $warehouse;
    private WarehouseLocation $location;
    private Vehicle $vehicle;
    private DeliveryWorkOrder $dwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Logistics State Machine Client SARL',
            'status'     => 'active',
        ]);

        $this->address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id'   => $this->client->id,
            'type'             => 'shipping',
            'label'            => 'Dépôt Client',
            'address_street'   => 'Route de Bizerte',
            'city'             => 'Ariana',
            'country'          => 'Tunisia',
            'is_primary'       => true,
        ]);

        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'UNIT'],
            ['name' => ['en' => 'Unit'], 'type' => 'count']
        );

        $category = \App\Modules\Catalog\Models\CatalogCategory::create([
            'name' => ['fr' => 'Catégorie Logistique'],
        ]);

        $this->item = CatalogItem::create([
            'code'         => 'ITEM-SM-' . uniqid(),
            'name'         => ['fr' => 'Machine State Item'],
            'item_type'    => 'product',
            'is_stockable' => true,
            'is_sellable'  => true,
            'category_id'  => $category->id,
            'uom_id'       => $uom->id,
            'status'       => 'active',
        ]);

        $this->variant = CatalogVariant::create([
            'catalog_item_id' => $this->item->id,
            'sku'             => 'SKU-SM-' . uniqid(),
            'name'            => 'Standard Variant',
            'is_default'      => true,
        ]);

        $this->warehouse = WarehouseSite::create([
            'code'   => 'WH-SM-' . uniqid(),
            'name'   => 'Entrepôt Megrine',
            'status' => 'active',
        ]);

        $zone = WarehouseZone::create([
            'site_id' => $this->warehouse->id,
            'code'    => 'ZONE-A-' . uniqid(),
            'name'    => 'Zone A',
            'type'    => 'standard',
        ]);

        $this->location = WarehouseLocation::create([
            'zone_id'       => $zone->id,
            'barcode'       => 'LOC-SM-' . uniqid(),
            'location_type' => 'shelf',
            'is_active'     => true,
        ]);

        // Stock in inventory
        StockItem::create([
            'catalog_item_id'    => $this->item->id,
            'catalog_variant_id' => $this->variant->id,
            'location_id'        => $this->location->id,
            'qty_on_hand'        => 50,
            'qty_reserved'       => 0,
            'qty_available'      => 50,
            'unit_cost'          => 15,
            'currency'           => 'TND',
            'status'             => 'active',
        ]);

        $this->vehicle = Vehicle::create([
            'plate_number' => 'TU-' . rand(100, 999) . '-SM',
            'label'        => 'Camionnette Iveco',
            'type'         => 'van',
            'capacity_kg'  => 1500,
            'status'       => 'available',
        ]);

        // Create accepted SO
        $so = SalesOrder::create([
            'client_id'        => $this->client->id,
            'status'           => SalesOrderStatus::Accepted,
            'so_type'          => SalesOrderType::Sales,
            'payment_terms'    => PaymentTerms::Immediate,
            'has_goods_lines'  => true,
            'currency'         => 'TND',
            'total_ht'         => 150,
            'total_ttc'        => 178.5,
        ]);

        $line = SalesOrderLine::create([
            'sales_order_id'       => $so->id,
            'catalog_item_id'      => $this->item->id,
            'variant_id'           => $this->variant->id,
            'item_type_snapshot'   => ItemTypeSnapshot::PhysicalProduct,
            'qty'                  => 10,
            'unit_price'           => 15,
            'total_ht'             => 150,
            'total_ttc'            => 178.5,
        ]);

        // Create DWO via creation service
        $creationService = app(DwoCreationService::class);
        $this->dwo = $creationService->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id'           => $this->warehouse->id,
            'scheduled_date'         => now()->toDateString(),
            'lines'                  => [
                ['so_line_id' => $line->id, 'qty_to_deliver' => 10],
            ],
        ], $this->user);
    }

    public function test_cannot_skip_states_illegally(): void
    {
        $dwoService = app(DWOService::class);

        $this->expectException(ValidationException::class);

        // Cannot jump directly from draft to dispatched
        $dwoService->transition($this->dwo, DwoStatus::Dispatched, $this->user);
    }

    public function test_full_successful_lifecycle_transition(): void
    {
        $dwoService = app(DWOService::class);
        $pickingService = app(DwoPickingService::class);

        // 1. Draft -> Planned
        $dwo = $dwoService->transition($this->dwo, DwoStatus::Planned, $this->user, [
            'vehicle_id' => $this->vehicle->id,
            'driver_id'  => $this->user->id,
        ]);
        $this->assertEquals(DwoStatus::Planned, $dwo->status);

        // 2. Planned -> ReadyToPick
        $dwo = $dwoService->transition($dwo, DwoStatus::ReadyToPick, $this->user);
        $this->assertEquals(DwoStatus::ReadyToPick, $dwo->status);

        // 3. ReadyToPick -> Picking
        $dwo = $dwoService->transition($dwo, DwoStatus::Picking, $this->user);
        $this->assertEquals(DwoStatus::Picking, $dwo->status);

        // Perform picking
        $line = $dwo->lines->first();
        $pickingService->recordPick($line, [
            'qty_picked' => 10,
            'lot_number' => 'LOT-001',
        ], $this->user->id);

        // 4. Picking -> ReadyToLoad
        $dwo = $dwoService->transition($dwo, DwoStatus::ReadyToLoad, $this->user);
        $this->assertEquals(DwoStatus::ReadyToLoad, $dwo->status);

        // 5. ReadyToLoad -> Loaded
        $dwo = $dwoService->transition($dwo, DwoStatus::Loaded, $this->user, [
            'vehicle_id' => $this->vehicle->id,
        ]);
        $this->assertEquals(DwoStatus::Loaded, $dwo->status);

        // 6. Loaded -> Dispatched (decrements inventory!)
        $dwo = $dwoService->transition($dwo, DwoStatus::Dispatched, $this->user);
        $this->assertEquals(DwoStatus::Dispatched, $dwo->status);
        $this->assertNotNull($dwo->dispatched_at);

        // 7. Dispatched -> InTransit
        $dwo = $dwoService->transition($dwo, DwoStatus::InTransit, $this->user);
        $this->assertEquals(DwoStatus::InTransit, $dwo->status);

        // Get the Delivery Note created by ensureDeliveryNoteCreated during InTransit transition
        $dwo->loadMissing('deliveryNote');
        $note = $dwo->deliveryNote;
        
        // Update to confirmed status
        $note->status = 'confirmed';
        $note->save();

        // Confirm and post stock
        app(DeliveryNoteStockService::class)->confirmAndPost($note, $this->user->id, [
            ['dwo_line_id' => $dwo->lines->first()->id, 'qty_delivered' => 10],
        ]);

        // 8. InTransit -> Delivered (with POD payload)
        $dwo = $dwoService->transition($dwo, DwoStatus::Delivered, $this->user, [
            'pod' => [
                'receiver_name'     => 'Mohamed Salah',
                'receiver_title'    => 'Chef de Chantier',
                'condition_overall' => 'good',
            ],
        ]);
        $this->assertEquals(DwoStatus::Delivered, $dwo->status);
        $this->assertNotNull($dwo->delivered_at);
        $this->assertNotNull($dwo->pod);
        $this->assertEquals('Mohamed Salah', $dwo->pod->receiver_name);

        // 9. Delivered -> Closed
        $dwo = $dwoService->transition($dwo, DwoStatus::Closed, $this->user);
        $this->assertEquals(DwoStatus::Closed, $dwo->status);
        $this->assertNotNull($dwo->closed_at);

        // Verify status histories count
        $histories = $dwo->statusHistories()->get();
        $this->assertGreaterThanOrEqual(9, $histories->count());
    }

    public function test_cancellation_releases_stock_reservations(): void
    {
        $dwoService = app(DWOService::class);

        // Move to ReadyToPick (creates reservations)
        $dwo = $dwoService->transition($this->dwo, DwoStatus::Planned, $this->user);
        $dwo = $dwoService->transition($dwo, DwoStatus::ReadyToPick, $this->user);

        // Cancel
        $dwo = $dwoService->transition($dwo, DwoStatus::Cancelled, $this->user, [
            'notes' => 'Commande annulée par le client',
        ]);

        $this->assertEquals(DwoStatus::Cancelled, $dwo->status);
        $this->assertEquals('cancelled', $dwo->lines->first()->item_status->value);
    }
}
