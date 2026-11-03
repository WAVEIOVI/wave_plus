<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class CatalogVariantsTest extends TestCase
{
    use RefreshDatabase;

    private UnitOfMeasure $uom;
    private CatalogCategory $category;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);

        $this->category = CatalogCategory::create([
            'name' => ['en' => 'Test Category'],
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->admin->assignRole('Super Admin');
        Sanctum::actingAs($this->admin);
    }

    private function createItem(): CatalogItem
    {
        $item = CatalogItem::create([
            'code'        => 'TEST-' . uniqid(),
            'name'        => ['en' => 'Test Item'],
            'item_type'   => 'product',
            'status'      => 'active',
            'uom_id'      => $this->uom->id,
            'category_id' => $this->category->id,
        ]);

        $item->variants()->create([
            'sku'        => $item->code,
            'is_default' => true,
            'status'     => 'active',
        ]);

        return $item;
    }

    /** @test */
    public function it_creates_a_default_variant_automatically_when_item_is_created_via_api(): void
    {
        $payload = [
            'code'        => 'API-ITEM-001',
            'name'        => ['en' => 'API Item'],
            'item_type'   => 'product',
            'status'      => 'active',
            'uom_id'      => $this->uom->id,
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/catalog/items', $payload);
        $response->assertStatus(201);

        $itemId = $response->json('data.id');
        $this->assertNotNull($itemId);

        $variants = CatalogVariant::where('catalog_item_id', $itemId)->get();
        $this->assertCount(1, $variants);
        $this->assertTrue((bool) $variants->first()->is_default);
    }

    /** @test */
    public function only_one_variant_can_be_default_at_a_time(): void
    {
        $item = $this->createItem();

        // Create a second variant
        $v2 = CatalogVariant::create([
            'catalog_item_id' => $item->id,
            'sku'             => 'SKU-2',
            'status'          => 'active',
            'is_default'      => false,
        ]);

        // Get the existing default variant
        $v1 = CatalogVariant::where('catalog_item_id', $item->id)->where('is_default', true)->first();
        $this->assertNotNull($v1);

        // Now make v2 default via the model (booted hook should unset v1)
        $v2->is_default = true;
        $v2->save();

        $this->assertFalse((bool) $v1->fresh()->is_default);
        $this->assertTrue((bool) $v2->fresh()->is_default);
    }

    /** @test */
    public function deleting_the_only_variant_is_prevented(): void
    {
        $item = $this->createItem();

        $variant = CatalogVariant::where('catalog_item_id', $item->id)->first();
        $this->assertNotNull($variant);
        $this->assertCount(1, CatalogVariant::where('catalog_item_id', $item->id)->get());

        $response = $this->deleteJson("/api/catalog/variants/{$variant->id}");
        $response->assertStatus(422);

        $this->assertDatabaseHas('catalog_variants', ['id' => $variant->id]);
    }

    /** @test */
    public function deleting_a_non_default_variant_succeeds_when_others_exist(): void
    {
        $item = $this->createItem();

        $v2 = CatalogVariant::create([
            'catalog_item_id' => $item->id,
            'sku'             => 'SKU-EXTRA',
            'status'          => 'active',
            'is_default'      => false,
        ]);

        $response = $this->deleteJson("/api/catalog/variants/{$v2->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('catalog_variants', ['id' => $v2->id]);
    }

    /** @test */
    public function variant_list_endpoint_returns_is_default_flag(): void
    {
        $item = $this->createItem();

        $response = $this->getJson("/api/catalog/items/{$item->id}/variants");
        $response->assertStatus(200);

        $variants = $response->json('data');
        $this->assertNotEmpty($variants);
        $this->assertArrayHasKey('is_default', $variants[0]);
    }

    /** @test */
    public function catalog_item_includes_variant_current_stock_from_stock_items(): void
    {
        $item = $this->createItem();
        $variant = CatalogVariant::where('catalog_item_id', $item->id)->firstOrFail();
        $site = WarehouseSite::create([
            'name' => ['en' => 'Main Warehouse'],
            'code' => 'WH-MAIN',
        ]);
        $zone = WarehouseZone::create([
            'site_id' => $site->id,
            'name' => ['en' => 'Zone A'],
            'code' => 'Z-A',
        ]);
        $location = WarehouseLocation::create([
            'zone_id' => $zone->id,
            'barcode' => 'LOC-A01',
        ]);

        StockItem::create([
            'catalog_item_id' => $item->id,
            'catalog_variant_id' => $variant->id,
            'location_id' => $location->id,
            'qty_on_hand' => 10,
            'qty_reserved' => 2,
            'qty_available' => 8,
            'qty_in_transit' => 0,
            'condition' => 'new',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/catalog/items?include=variants.stockItems');
        $response->assertOk();

        $data = collect($response->json('data'))->firstWhere('id', $item->id);
        $this->assertEquals(8.0, (float) $data['current_stock']);
        $this->assertEquals(8.0, (float) $data['variants'][0]['current_stock']);

        $variantResponse = $this->getJson("/api/catalog/items/{$item->id}/variants");
        $variantResponse->assertOk();
        $this->assertEquals(8.0, (float) $variantResponse->json('data.0.current_stock'));
    }
}
