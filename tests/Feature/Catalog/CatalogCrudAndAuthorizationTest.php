<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Models\QuotationLine;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Crm\Services\QuotationService;
use App\Modules\Crm\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class CatalogCrudAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private UnitOfMeasure $uom;

    private CatalogCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece', 'fr' => 'Pièce'],
            'type' => 'count',
        ]);

        $this->category = CatalogCategory::create([
            'name' => ['en' => 'Pumps', 'fr' => 'Pompes'],
            'is_active' => true,
        ]);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    private function actingWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        Sanctum::actingAs($user);

        return $user;
    }

    private function itemPayload(array $overrides = []): array
    {
        $code = $overrides['code'] ?? 'PROD-'.rand(10000, 99999);

        return array_merge([
            'code' => $code,
            'item_type' => 'product',
            'name' => ['en' => 'Hydraulic Pump', 'fr' => 'Pompe hydraulique'],
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'status' => 'active',
            'is_stockable' => true,
            'is_sellable' => true,
            'product' => ['product_type' => 'physical'],
            'variants' => [[
                'sku' => $code.'-SKU',
                'price' => 125.5,
                'cost_price' => 80,
                'tax_class' => 'standard_19pct',
            ]],
        ], $overrides);
    }

    public function test_unauthorized_user_cannot_access_catalog(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/catalog/items')->assertForbidden();
        $this->postJson('/api/catalog/items', $this->itemPayload())->assertForbidden();
    }

    public function test_reader_can_list_but_cannot_create(): void
    {
        $this->actingWithPermissions(['read-Catalog']);

        $this->getJson('/api/catalog/items')->assertOk();
        $this->postJson('/api/catalog/items', $this->itemPayload())->assertForbidden();
    }

    public function test_can_create_search_and_show_item_with_variant(): void
    {
        $this->actingAsSuperAdmin();

        $create = $this->postJson('/api/catalog/items', $this->itemPayload([
            'code' => 'PUMP-WAVE',
            'name' => ['en' => 'Wave Pump', 'fr' => 'Pompe Wave'],
            'variants' => [[
                'sku' => 'PUMP-WAVE-SKU',
                'price' => 99.9,
            ]],
        ]));

        $create->assertCreated();
        $create->assertJsonPath('data.code', 'PUMP-WAVE');
        $this->assertNotEmpty($create->json('data.id'));
        $this->assertCount(1, CatalogVariant::where('catalog_item_id', $create->json('data.id'))->get());
        $this->assertTrue((bool) CatalogVariant::where('sku', 'PUMP-WAVE-SKU')->first()?->is_default);

        $this->postJson('/api/catalog/items', $this->itemPayload([
            'code' => 'OTHER-ITEM',
            'name' => ['en' => 'Other Item', 'fr' => 'Autre'],
            'variants' => [['sku' => 'OTHER-SKU', 'price' => 10]],
        ]))->assertCreated();

        $search = $this->getJson('/api/catalog/items?q=Wave');
        $search->assertOk();
        $this->assertSame(1, $search->json('total'));
        $this->assertSame('PUMP-WAVE', $search->json('data.0.code'));
    }

    public function test_validation_failure_on_item_create(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/catalog/items', [
            'code' => '',
            'item_type' => 'product',
        ])->assertStatus(422);
    }

    public function test_items_meta_and_options_are_not_captured_as_ids(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/catalog/items', $this->itemPayload([
            'code' => 'LOOKUP-1',
            'name' => ['en' => 'Lookup Item', 'fr' => 'Lookup'],
            'variants' => [['sku' => 'LOOKUP-SKU', 'price' => 12]],
        ]))->assertCreated();

        $this->getJson('/api/catalog/items/meta')->assertOk()->assertJsonPath('data.total_items', 1);

        $options = $this->getJson('/api/catalog/items/options?q=Lookup');
        $options->assertOk();
        $options->assertJsonPath('data.0.code', 'LOOKUP-1');
        $this->assertArrayHasKey('sku', $options->json('data.0'));
        $this->assertArrayNotHasKey('internal_notes', $options->json('data.0'));
        $this->assertArrayNotHasKey('variants', $options->json('data.0'));
    }

    public function test_legacy_unbounded_list_is_capped(): void
    {
        $this->actingAsSuperAdmin();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/catalog/items', $this->itemPayload([
                'code' => "CAP-{$i}",
                'variants' => [['sku' => "CAP-SKU-{$i}", 'price' => 1]],
            ]))->assertCreated();
        }

        $response = $this->getJson('/api/catalog/items?itemsPerPage=-1');
        $response->assertOk();
        $this->assertSame(3, $response->json('total'));
        $this->assertCount(3, $response->json('data'));
    }

    public function test_category_brand_and_uom_round_trip(): void
    {
        $this->actingAsSuperAdmin();

        $category = $this->postJson('/api/catalog/categories', [
            'name' => ['en' => 'Safety', 'fr' => 'Sécurité'],
        ])->assertCreated();

        $id = $category->json('data.id');
        $this->putJson("/api/catalog/categories/{$id}", [
            'name' => ['en' => 'Safety Gear', 'fr' => 'EPI'],
        ])->assertOk()->assertJsonPath('data.name.en', 'Safety Gear');

        $brand = $this->postJson('/api/catalog/brands', [
            'name' => 'Acme',
            'code' => 'ACME',
        ])->assertCreated();

        $this->getJson('/api/catalog/brands?q=Acme')->assertOk();
        $this->assertGreaterThanOrEqual(1, count($this->getJson('/api/catalog/uom')->json('data')));
        $this->deleteJson('/api/catalog/brands/'.$brand->json('data.id'))->assertOk();
    }

    public function test_updating_variant_price_does_not_change_existing_document_prices_or_snapshots(): void
    {
        $this->actingAsSuperAdmin();

        $created = $this->postJson('/api/catalog/items', $this->itemPayload([
            'code' => 'SNAP-ITEM',
            'name' => ['en' => 'Snapshot Pump', 'fr' => 'Pompe snapshot'],
            'variants' => [['sku' => 'SNAP-SKU', 'price' => 40]],
        ]))->assertCreated();

        $itemId = $created->json('data.id');
        $item = CatalogItem::with('variants')->findOrFail($itemId);
        $variant = $item->variants->first();

        $client = Client::create([
            'legal_name' => 'Acme SARL',
            'status' => 'active',
        ]);

        $so = app(SalesOrderService::class)->createSalesOrder([
            'client_id' => $client->id,
            'so_type' => 'sales',
            'payment_terms' => 'net_30',
            'currency' => 'TND',
            'lines' => [[
                'catalog_item_id' => $item->id,
                'variant_id' => $variant->id,
                'qty' => 2,
                'unit_price' => 40,
                'item_type_snapshot' => 'physical_product',
            ]],
        ]);

        $quotation = app(QuotationService::class)->createQuotation([
            'client_id' => $client->id,
            'valid_until' => now()->addDays(10)->toDateString(),
            'status' => 'draft',
            'payment_terms' => 'net_30',
            'currency' => 'TND',
            'lines' => [[
                'catalog_item_id' => $item->id,
                'variant_id' => $variant->id,
                'qty' => 1,
                'unit_price' => 40,
                    'item_type_snapshot' => 'physical_product',
                    'unit_price_after_discount' => 40,
                    'total_ht' => 40,
                'total_tva' => 7.6,
                'total_ttc' => 47.6,
            ]],
        ]);

        $this->putJson("/api/catalog/items/{$itemId}", [
            'name' => ['en' => 'Renamed Pump', 'fr' => 'Pompe renommée'],
            'variants' => [[
                'id' => $variant->id,
                'sku' => 'SNAP-SKU',
                'price' => 999,
                'is_default' => true,
            ]],
        ])->assertOk();

        $soLine = SalesOrderLine::where('sales_order_id', $so->id)->first();
        $this->assertSame('40.000', (string) $soLine->unit_price);
        $this->assertSame('Snapshot Pump', $soLine->description_snapshot);
        $this->assertSame('SNAP-SKU', $soLine->sku_snapshot);
        $this->assertSame('PCE', $soLine->uom_code_snapshot);

        $qLine = QuotationLine::where('quotation_id', $quotation->id)->first();
        $this->assertSame('40.000', (string) $qLine->unit_price);
        $this->assertSame('Snapshot Pump', $qLine->description_snapshot);
        $this->assertSame('SNAP-SKU', $qLine->sku_snapshot);
        $this->assertSame('PCE', $qLine->uom_code_snapshot);

        $this->assertSame('999.000', (string) $variant->fresh()->price);
    }
}
