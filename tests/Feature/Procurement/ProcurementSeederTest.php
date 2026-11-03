<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use Database\Seeders\ProcurementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_seeder_seeds_ipr_and_purchase_order(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'legal_name' => 'Apex Industrial Supplies',
            'supplier_category' => 'distributor',
            'status' => 'active',
        ]);
        $uom = UnitOfMeasure::create([
            'code' => 'EA',
            'name' => ['en' => 'Each'],
            'type' => 'count',
        ]);
        $category = CatalogCategory::create([
            'name' => ['en' => 'Safety Gear'],
            'is_active' => true,
        ]);
        $item = CatalogItem::create([
            'code' => 'SAFE-001',
            'name' => ['en' => 'Fire Extinguisher ABC 6kg'],
            'item_type' => 'product',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'status' => 'active',
        ]);
        $variant = CatalogVariant::create([
            'catalog_item_id' => $item->id,
            'sku' => 'SAFE-001-STD',
            'price' => 45.0,
            'is_default' => true,
        ]);

        $seeder = new ProcurementSeeder;
        $seeder->run();

        // Verify IPR was seeded
        $this->assertDatabaseHas('internal_purchase_requests', [
            'requested_by' => $user->id,
            'status' => 'approved',
        ]);

        $ipr = InternalPurchaseRequest::where('requested_by', $user->id)->first();
        $this->assertNotNull($ipr);
        $this->assertCount(1, $ipr->lines);
        $this->assertEquals(10.00, $ipr->lines->first()->qty_requested);
        $this->assertStringStartsWith('IPR-', $ipr->reference);

        // Verify Purchase Order was seeded
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'status' => 'sent',
            'total_ht' => 450.000,
        ]);

        $po = PurchaseOrder::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($po);
        $this->assertCount(1, $po->lines);
        $this->assertEquals(45.000, $po->lines->first()->unit_price_ht);
        $this->assertStringStartsWith('PO-', $po->reference);

        // Test idempotency — second run must not create duplicates
        $seeder->run();
        $this->assertEquals(1, InternalPurchaseRequest::count());
        $this->assertEquals(1, PurchaseOrder::count());
    }

    public function test_procurement_seeder_safely_skips_when_prerequisites_missing(): void
    {
        // No users, suppliers, or items exist
        $seeder = new ProcurementSeeder;
        $seeder->run();

        $this->assertEquals(0, InternalPurchaseRequest::count());
        $this->assertEquals(0, PurchaseOrder::count());
    }
}
