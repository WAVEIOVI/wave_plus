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
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Services\DwoCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class DwoCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private BpAddress $address;
    private CatalogItem $item;
    private CatalogVariant $variant;
    private WarehouseSite $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Acme Logistics Client SARL',
            'status'     => 'active',
        ]);

        $this->address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id'   => $this->client->id,
            'type'             => 'shipping',
            'label'            => 'Siège Principal',
            'address_street'   => '123 Avenue Habib Bourguiba',
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
            'code'         => 'PROD-LOG-' . uniqid(),
            'name'         => ['fr' => 'Produit Logistique Test'],
            'item_type'    => 'product',
            'is_stockable' => true,
            'is_sellable'  => true,
            'category_id'  => $category->id,
            'uom_id'       => $uom->id,
            'status'       => 'active',
        ]);

        $this->variant = CatalogVariant::create([
            'catalog_item_id' => $this->item->id,
            'sku'             => 'SKU-LOG-' . uniqid(),
            'name'            => 'Standard Variant',
            'is_default'      => true,
        ]);

        $this->warehouse = WarehouseSite::create([
            'code'   => 'WH-LOG-' . uniqid(),
            'name'   => 'Entrepôt Central Rades',
            'status' => 'active',
        ]);
    }

    public function test_cannot_create_dwo_if_sales_order_is_not_accepted(): void
    {
        $so = SalesOrder::create([
            'client_id'        => $this->client->id,
            'status'           => SalesOrderStatus::Draft,
            'so_type'          => SalesOrderType::Sales,
            'payment_terms'    => PaymentTerms::Immediate,
            'has_goods_lines'  => true,
            'currency'         => 'TND',
            'total_ht'         => 100,
            'total_ttc'        => 119,
        ]);

        $line = SalesOrderLine::create([
            'sales_order_id'     => $so->id,
            'catalog_item_id'    => $this->item->id,
            'variant_id'         => $this->variant->id,
            'item_type_snapshot' => ItemTypeSnapshot::PhysicalProduct,
            'qty'                => 10,
            'unit_price'         => 10,
            'total_ht'           => 100,
            'total_ttc'          => 119,
        ]);

        $service = app(DwoCreationService::class);

        $this->expectException(ValidationException::class);

        $service->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'lines'                  => [
                ['so_line_id' => $line->id, 'qty_to_deliver' => 5],
            ],
        ], $this->user);
    }

    public function test_successfully_creates_dwo_from_accepted_sales_order(): void
    {
        $so = SalesOrder::create([
            'client_id'        => $this->client->id,
            'status'           => SalesOrderStatus::Accepted,
            'so_type'          => SalesOrderType::Sales,
            'payment_terms'    => PaymentTerms::Immediate,
            'has_goods_lines'  => true,
            'currency'         => 'TND',
            'total_ht'         => 200,
            'total_ttc'        => 238,
        ]);

        $line = SalesOrderLine::create([
            'sales_order_id'       => $so->id,
            'catalog_item_id'      => $this->item->id,
            'variant_id'           => $this->variant->id,
            'item_type_snapshot'   => ItemTypeSnapshot::PhysicalProduct,
            'description_snapshot' => 'Produit Logistique Test',
            'qty'                  => 20,
            'unit_price'           => 10,
            'total_ht'             => 200,
            'total_ttc'            => 238,
        ]);

        $service = app(DwoCreationService::class);

        $dwo = $service->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'warehouse_id'           => $this->warehouse->id,
            'scheduled_date'         => '2026-09-20',
            'priority'               => 'high',
            'notes'                  => 'Livraison urgente matin',
            'lines'                  => [
                ['so_line_id' => $line->id, 'qty_to_deliver' => 12],
            ],
        ], $this->user);

        $this->assertNotNull($dwo->id);
        $this->assertEquals(DwoStatus::Draft, $dwo->status);
        $this->assertEquals(1, $dwo->lines->count());

        $dwoLine = $dwo->lines->first();
        $this->assertEquals(12.0, (float) $dwoLine->qty_allocated);
        $this->assertEquals(20.0, (float) $dwoLine->qty_ordered);
        $this->assertEquals($this->address->id, $dwo->delivery_bp_address_id);
        $this->assertNotNull($dwo->delivery_address_snapshot);
        $this->assertEquals('Tunis', $dwo->delivery_address_snapshot['city']);

        // Check remaining calculation
        $eligible = $service->getEligibleSalesOrderLines($so);
        $this->assertEquals(8.0, $eligible[0]['qty_remaining']);
    }

    public function test_cannot_exceed_remaining_sales_order_quantity(): void
    {
        $so = SalesOrder::create([
            'client_id'        => $this->client->id,
            'status'           => SalesOrderStatus::Accepted,
            'so_type'          => SalesOrderType::Sales,
            'payment_terms'    => PaymentTerms::Immediate,
            'has_goods_lines'  => true,
            'currency'         => 'TND',
            'total_ht'         => 100,
            'total_ttc'        => 119,
        ]);

        $line = SalesOrderLine::create([
            'sales_order_id'       => $so->id,
            'catalog_item_id'      => $this->item->id,
            'variant_id'           => $this->variant->id,
            'item_type_snapshot'   => ItemTypeSnapshot::PhysicalProduct,
            'qty'                  => 5,
            'unit_price'           => 20,
            'total_ht'             => 100,
            'total_ttc'            => 119,
        ]);

        $service = app(DwoCreationService::class);

        $this->expectException(ValidationException::class);

        // Attempt to deliver 8 when only 5 was ordered
        $service->createFromSalesOrder($so, [
            'delivery_bp_address_id' => $this->address->id,
            'lines'                  => [
                ['so_line_id' => $line->id, 'qty_to_deliver' => 8],
            ],
        ], $this->user);
    }
}
