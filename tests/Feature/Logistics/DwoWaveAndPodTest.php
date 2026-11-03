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
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Logistics\Enums\DeliveryWaveStatus;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryWave;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Logistics\Services\DeliveryExceptionService;
use App\Modules\Logistics\Services\DeliveryWaveService;
use App\Modules\Logistics\Services\DwoCreationService;
use App\Modules\Logistics\Services\PodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class DwoWaveAndPodTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private BpAddress $address;
    private CatalogItem $item;
    private CatalogVariant $variant;
    private WarehouseSite $warehouse;
    private Vehicle $vehicle;
    private DeliveryWorkOrder $dwo1;
    private DeliveryWorkOrder $dwo2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Wave Route Client SARL',
            'status'     => 'active',
        ]);

        $this->address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id'   => $this->client->id,
            'type'             => 'shipping',
            'label'            => 'Chantier Nord',
            'address_street'   => 'Zone Industrielle Charguia 2',
            'city'             => 'Tunis',
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
            'code'         => 'ITEM-WAVE-' . uniqid(),
            'name'         => ['fr' => 'Wave Article Test'],
            'item_type'    => 'product',
            'is_stockable' => true,
            'is_sellable'  => true,
            'category_id'  => $category->id,
            'uom_id'       => $uom->id,
            'status'       => 'active',
        ]);

        $this->variant = CatalogVariant::create([
            'catalog_item_id' => $this->item->id,
            'sku'             => 'SKU-WAVE-' . uniqid(),
            'name'            => 'Standard Variant',
            'is_default'      => true,
        ]);

        $this->warehouse = WarehouseSite::create([
            'code'   => 'WH-WV-' . uniqid(),
            'name'   => 'Entrepôt La Goulette',
            'status' => 'active',
        ]);

        $this->vehicle = Vehicle::create([
            'plate_number' => 'TU-' . rand(100, 999) . '-WV',
            'label'        => 'Camion Tournée',
            'type'         => 'truck',
            'capacity_kg'  => 3500,
            'status'       => 'available',
        ]);

        $so = SalesOrder::create([
            'client_id'        => $this->client->id,
            'status'           => SalesOrderStatus::Accepted,
            'so_type'          => SalesOrderType::Sales,
            'payment_terms'    => PaymentTerms::Immediate,
            'has_goods_lines'  => true,
            'currency'         => 'TND',
            'total_ht'         => 300,
            'total_ttc'        => 357,
        ]);

        $line1 = SalesOrderLine::create([
            'sales_order_id'       => $so->id,
            'catalog_item_id'      => $this->item->id,
            'variant_id'           => $this->variant->id,
            'item_type_snapshot'   => ItemTypeSnapshot::PhysicalProduct,
            'qty'                  => 10,
            'unit_price'           => 15,
            'total_ht'             => 150,
            'total_ttc'            => 178.5,
        ]);

        $line2 = SalesOrderLine::create([
            'sales_order_id'       => $so->id,
            'catalog_item_id'      => $this->item->id,
            'variant_id'           => $this->variant->id,
            'item_type_snapshot'   => ItemTypeSnapshot::PhysicalProduct,
            'qty'                  => 10,
            'unit_price'           => 15,
            'total_ht'             => 150,
            'total_ttc'            => 178.5,
        ]);

        $creationService = app(DwoCreationService::class);

        $this->dwo1 = $creationService->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id'           => $this->warehouse->id,
            'lines'                  => [
                ['so_line_id' => $line1->id, 'qty_to_deliver' => 10],
            ],
        ], $this->user);

        $this->dwo2 = $creationService->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id'           => $this->warehouse->id,
            'lines'                  => [
                ['so_line_id' => $line2->id, 'qty_to_deliver' => 10],
            ],
        ], $this->user);
    }

    public function test_can_create_delivery_wave_and_sequence_stops(): void
    {
        $waveService = app(DeliveryWaveService::class);

        $wave = $waveService->createWave([
            'planned_date'  => '2026-09-25',
            'dispatch_time' => '07:30',
            'warehouse_id'  => $this->warehouse->id,
            'vehicle_id'    => $this->vehicle->id,
            'driver_id'     => $this->user->id,
            'route_notes'   => 'Tournée Grand Tunis',
            'dwo_ids'       => [$this->dwo1->id, $this->dwo2->id],
        ], $this->user);

        $this->assertNotNull($wave->id);
        $this->assertEquals(DeliveryWaveStatus::Draft, $wave->status);
        $this->assertEquals(2, $wave->waveItems->count());
        $this->assertEquals(2, $wave->stops->count());

        // Stop sequences
        $stops = $wave->stops->sortBy('sequence')->values();
        $this->assertEquals($this->dwo1->id, $stops[0]->dwo_id);
        $this->assertEquals(1, $stops[0]->sequence);
        $this->assertEquals($this->dwo2->id, $stops[1]->dwo_id);
        $this->assertEquals(2, $stops[1]->sequence);

        // Reorder stops
        $waveService->reorderStops($wave, [
            ['stop_id' => $stops[0]->id, 'sequence' => 2],
            ['stop_id' => $stops[1]->id, 'sequence' => 1],
        ]);

        $freshStops = $wave->fresh()->stops->sortBy('sequence')->values();
        $this->assertEquals($this->dwo2->id, $freshStops[0]->dwo_id);
        $this->assertEquals(1, $freshStops[0]->sequence);
    }

    public function test_can_record_pod_and_exceptions(): void
    {
        $podService = app(PodService::class);
        $exceptionService = app(DeliveryExceptionService::class);

        // Record POD
        $pod = $podService->recordPod($this->dwo1, [
            'receiver_name'     => 'Ali Ben Salem',
            'receiver_title'    => 'Magasinier',
            'condition_overall' => 'good',
            'notes'             => 'Reçu en bon état',
        ], $this->user);

        $this->assertNotNull($pod->id);
        $this->assertEquals('Ali Ben Salem', $pod->receiver_name);

        // Record Exception
        $exception = $exceptionService->recordException($this->dwo1, [
            'reason'       => 'damaged_goods',
            'affected_qty' => 2,
            'notes'        => '2 unités avec emballage déchiré',
        ], $this->user);

        $this->assertNotNull($exception->id);
        $this->assertEquals('damaged_goods', $exception->reason->value);
        $this->assertEquals('open', $exception->status);

        // Resolve Exception
        $resolved = $exceptionService->resolveException($exception, [
            'resolution_notes' => 'Remplacement immédiat effectué',
        ], $this->user);

        $this->assertEquals('resolved', $resolved->status);
        $this->assertEquals($this->user->id, $resolved->resolved_by);
    }
}
