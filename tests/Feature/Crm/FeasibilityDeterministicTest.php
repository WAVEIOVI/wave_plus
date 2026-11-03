<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Services\FeasibilityService;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class FeasibilityDeterministicTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private CatalogItem $stockableItem;
    private CatalogItem $serviceItem;
    private WarehouseLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Metro Industries',
            'commercial_name' => 'Metro',
            'client_id' => 'CL-METRO-01',
            'status' => 'active',
        ]);

        $uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);

        $category = CatalogCategory::create([
            'name' => 'Mechanical Parts',
            'code' => 'CAT-MECH',
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
            'barcode' => 'LOC-A01',
        ]);

        $this->stockableItem = CatalogItem::create([
            'code' => 'MECH-001',
            'name' => ['en' => 'Heavy Bearing'],
            'item_type' => 'product',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'base_price' => 100.0,
            'is_stockable' => true,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        CatalogVariant::create([
            'catalog_item_id' => $this->stockableItem->id,
            'sku' => 'MECH-001-STD',
            'name' => 'Standard Bearing',
            'price' => 100.0,
            'is_default' => true,
        ]);

        $this->serviceItem = CatalogItem::create([
            'code' => 'SVC-PUMP',
            'name' => ['en' => 'Pump Maintenance'],
            'item_type' => 'eq_servicing',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'base_price' => 200.0,
            'is_stockable' => false,
            'is_sellable' => true,
            'status' => 'active',
        ]);
    }

    public function test_failed_credit_check_blocks_approval(): void
    {
        // SO total exceeds credit limit
        $so = SalesOrder::create([
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'status' => SalesOrderStatus::UnderReview,
            'total_ttc' => 200000.0, // Exceeds default 100,000 limit
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/feasibility-review", [
            'decision' => 'approved',
            'credit_check_result' => 'exceeded', // Or computed by service
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Credit check failed', $res->json('message'));
        $this->assertEquals(FeasibilityStatus::Pending, $so->fresh()->feasibility_status);
    }

    public function test_warning_credit_check_allows_approval(): void
    {
        $so = SalesOrder::create([
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'status' => SalesOrderStatus::UnderReview,
            'total_ttc' => 90000.0, // > 85% of 100,000 -> warning
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/feasibility-review", [
            'decision' => 'approved',
            'credit_check_result' => 'warning',
            'stock_check_result' => 'sufficient',
        ]);

        $res->assertOk();
        $this->assertEquals(FeasibilityStatus::Approved, $so->fresh()->feasibility_status);
    }

    public function test_insufficient_stock_blocks_sales_order_approval(): void
    {
        $so = SalesOrder::create([
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'status' => SalesOrderStatus::UnderReview,
            'total_ttc' => 1000.0,
        ]);

        $so->lines()->create([
            'catalog_item_id' => $this->stockableItem->id,
            'qty' => 10.0,
            'unit_price' => 100.0,
            'item_type_snapshot' => 'physical_product',
        ]);

        // Zero stock available -> insufficient
        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/feasibility-review", [
            'decision' => 'approved',
            'stock_check_result' => 'insufficient',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Stock check failed', $res->json('message'));
    }

    public function test_partial_stock_allows_sales_order_approval(): void
    {
        $so = SalesOrder::create([
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'status' => SalesOrderStatus::UnderReview,
            'total_ttc' => 1000.0,
        ]);

        $so->lines()->create([
            'catalog_item_id' => $this->stockableItem->id,
            'qty' => 10.0,
            'unit_price' => 100.0,
            'item_type_snapshot' => 'physical_product',
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/feasibility-review", [
            'decision' => 'approved',
            'credit_check_result' => 'sufficient',
            'stock_check_result' => 'partial',
        ]);

        $res->assertOk();
        $this->assertEquals(FeasibilityStatus::Approved, $so->fresh()->feasibility_status);
    }

    public function test_unavailable_capacity_blocks_service_order_approval(): void
    {
        $so = SalesOrder::create([
            'so_type' => 'service',
            'client_id' => $this->client->id,
            'payment_terms' => 'immediate',
            'status' => SalesOrderStatus::UnderReview,
            'total_ttc' => 500.0,
        ]);

        $so->lines()->create([
            'catalog_item_id' => $this->serviceItem->id,
            'qty' => 1.0,
            'unit_price' => 200.0,
            'item_type_snapshot' => 'service',
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/feasibility-review", [
            'decision' => 'approved',
            'capacity_check_result' => 'unavailable',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Capacity check failed', $res->json('message'));
    }
}
