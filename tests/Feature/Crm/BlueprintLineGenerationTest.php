<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\BlueprintOption;
use App\Modules\Catalog\Models\BlueprintOptionValue;
use App\Modules\Catalog\Models\BlueprintServicing;
use App\Modules\Catalog\Models\BlueprintServicingConsumable;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogEqServicing;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Services\BlueprintLineBuilderService;
use App\Modules\Crm\Models\PriceList;
use App\Modules\Crm\Models\PriceRequest;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class BlueprintLineGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private EquipmentClass $equipmentClass;
    private EquipmentBlueprint $blueprint;
    private BlueprintOption $capacityOption;
    private BlueprintOptionValue $val100;
    private BlueprintOptionValue $val200;
    private CatalogItem $servicingItem;
    private BlueprintServicing $servicing;
    private CatalogItem $consumableItem;
    private BlueprintServicingConsumable $consumable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Industrial Plant Co',
            'commercial_name' => 'IPC',
            'client_id' => 'CL-IPC-01',
            'status' => 'active',
        ]);

        $uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);

        $category = CatalogCategory::create([
            'name' => 'Heavy Machinery',
            'code' => 'CAT-MACH',
        ]);

        // Equipment Class
        $this->equipmentClass = EquipmentClass::create([
            'code' => 'EQ-PUMP',
            'name' => ['en' => 'Industrial Pumps'],
            'status' => 'active',
        ]);

        // Blueprint
        $this->blueprint = EquipmentBlueprint::create([
            'code' => 'BP-PUMP-X',
            'name' => ['en' => 'Industrial Pump X'],
            'status' => 'active',
            'equipment_class_id' => $this->equipmentClass->id,
        ]);

        // Option: Capacity
        $this->capacityOption = BlueprintOption::create([
            'blueprint_id' => $this->blueprint->id,
            'code' => 'CAPACITY',
            'name' => ['en' => 'Pump Capacity'],
            'type' => 'select',
            'sort_order' => 1,
        ]);

        $this->val100 = BlueprintOptionValue::create([
            'option_id' => $this->capacityOption->id,
            'code' => '100L',
            'value' => ['en' => '100 Liters'],
            'numeric_value' => 100.0,
            'unit' => 'L',
        ]);

        $this->val200 = BlueprintOptionValue::create([
            'option_id' => $this->capacityOption->id,
            'code' => '200L',
            'value' => ['en' => '200 Liters'],
            'numeric_value' => 200.0,
            'unit' => 'L',
        ]);

        // Servicing Item
        $this->servicingItem = CatalogItem::create([
            'code' => 'SVC-ANNUAL',
            'name' => ['en' => 'Annual Overhaul Service'],
            'item_type' => 'eq_servicing',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'base_price' => 500.0,
            'is_stockable' => false,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        CatalogEqServicing::create([
            'catalog_item_id' => $this->servicingItem->id,
            'eq_servicing_type' => 'maintenance_and_repair',
            'delivery_mode' => 'on_site',
        ]);

        $this->servicing = BlueprintServicing::create([
            'blueprint_id' => $this->blueprint->id,
            'servicing_item_id' => $this->servicingItem->id,
            'applicability_conditions' => null, // Always applicable
        ]);

        // Consumable Item (Oil)
        $this->consumableItem = CatalogItem::create([
            'code' => 'OIL-SYN',
            'name' => ['en' => 'Synthetic Lubricant Oil'],
            'item_type' => 'consumable',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'base_price' => 25.0,
            'is_stockable' => true,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        // Single variant auto-selected
        CatalogVariant::create([
            'catalog_item_id' => $this->consumableItem->id,
            'sku' => 'OIL-SYN-5L',
            'name' => '5L Can',
            'price' => 25.0,
            'cost_price' => 10.0,
            'markup' => 30.0,
            'is_default' => true,
        ]);

        // Consumable calculation: formula multiply numeric_value * 0.1
        $this->consumable = BlueprintServicingConsumable::create([
            'blueprint_servicing_id' => $this->servicing->id,
            'consumable_item_id' => $this->consumableItem->id,
            'calculation_type' => 'blueprint_option',
            'calculation_source_option_id' => $this->capacityOption->id,
            'calculation_method' => 'multiply',
            'calculation_parameter' => 0.1,
            'fixed_quantity' => 1.0,
        ]);
    }

    public function test_can_resolve_blueprint_lines_with_calculated_quantities(): void
    {
        $builder = app(BlueprintLineBuilderService::class);

        // 100L capacity -> 100 * 0.1 = 10.0 units of oil
        $resolution = $builder->resolve(
            blueprint: $this->blueprint,
            selectedOptionValueIds: [$this->val100->id],
            equipmentQty: 2.0, // 2 pumps
        );

        $this->assertFalse($resolution->hasMissingVariants());
        $this->assertCount(2, $resolution->lines); // 1 servicing line + 1 consumable line

        $servicingLine = collect($resolution->lines)->firstWhere('sourceServicingId', $this->servicing->id);
        $this->assertNotNull($servicingLine);
        $this->assertEquals(2.0, $servicingLine->qty); // 1 * 2 equipment

        $consumableLine = collect($resolution->lines)->firstWhere('sourceConsumableId', $this->consumable->id);
        $this->assertNotNull($consumableLine);
        // unit qty = 100 * 0.1 = 10.0; total qty = 10.0 * 2.0 equipment = 20.0
        $this->assertEquals(20.0, $consumableLine->qty);
        $this->assertEquals(25.0, $consumableLine->unitPrice);
    }

    public function test_preview_api_endpoint_returns_resolved_lines_and_equipment_class(): void
    {
        $res = $this->postJson("/api/catalog/blueprints/{$this->blueprint->id}/resolve-lines", [
            'selected_option_values' => [$this->val200->id],
            'equipment_qty' => 1.0,
        ]);

        $res->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertCount(2, $res->json('data.lines'));

        // 200 * 0.1 = 20 units
        $consumableLine = collect($res->json('data.lines'))->firstWhere('source_consumable_id', $this->consumable->id);
        $this->assertEquals(20.0, (float) $consumableLine['qty']);
        $this->assertSame('OIL-SYN', $consumableLine['catalog_item_code']);
        $this->assertSame('OIL-SYN-5L', $consumableLine['variant_sku']);
        $this->assertEquals(25.0, (float) $consumableLine['unit_price']);
        $this->assertEquals(10.0, (float) $consumableLine['variant_cost_price']);
        $this->assertEquals(30.0, (float) $consumableLine['variant_markup']);

        // Check blueprint listing returns equipment_class
        $indexRes = $this->getJson('/api/catalog/blueprints');
        $indexRes->assertOk();
        $bpData = collect($indexRes->json('data'))->firstWhere('id', $this->blueprint->id);
        $this->assertNotNull($bpData);
        $this->assertEquals($this->equipmentClass->id, $bpData['equipment_class_id']);
        $this->assertNotNull($bpData['equipment_class']);
        $this->assertEquals(1, $bpData['blueprint_options_count']);
        $this->assertEquals(1, $bpData['servicing_items_count']);
    }

    public function test_can_add_blueprint_lines_to_draft_sales_order(): void
    {
        $so = SalesOrder::create([
            'so_type' => 'service',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'status' => 'draft',
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/lines/from-blueprint", [
            'blueprint_id' => $this->blueprint->id,
            'selected_option_values' => [$this->val100->id],
            'equipment_qty' => 1.0,
        ]);

        $res->assertOk();

        // Check SalesOrderLines table
        $this->assertDatabaseHas('sales_order_lines', [
            'sales_order_id' => $so->id,
            'source_type' => 'blueprint',
            'source_blueprint_id' => $this->blueprint->id,
            'source_servicing_id' => $this->servicing->id,
        ]);

        $this->assertDatabaseHas('sales_order_lines', [
            'sales_order_id' => $so->id,
            'source_type' => 'blueprint',
            'source_consumable_id' => $this->consumable->id,
            'qty' => 10.0,
        ]);

        // Lines have provenance snapshot
        $soLine = $so->lines()->where('source_consumable_id', $this->consumable->id)->first();
        $this->assertNotNull($soLine->calculation_snapshot);
        $this->assertEquals('consumable', $soLine->calculation_snapshot['type']);
    }

    public function test_can_add_blueprint_lines_to_price_request(): void
    {
        $pr = PriceRequest::create([
            'client_id' => $this->client->id,
            'source' => \App\Modules\Crm\Enums\RfqSource::Email,
            'received_at' => now(),
            'status' => \App\Modules\Crm\Enums\RfqStatus::New,
        ]);

        $res = $this->postJson("/api/crm/price-requests/{$pr->id}/lines/from-blueprint", [
            'blueprint_id' => $this->blueprint->id,
            'selected_option_values' => [$this->val100->id],
            'equipment_qty' => 3.0,
        ]);

        $res->assertOk();

        $this->assertDatabaseHas('price_request_lines', [
            'price_request_id' => $pr->id,
            'source_type' => 'blueprint',
            'source_blueprint_id' => $this->blueprint->id,
            'source_servicing_id' => $this->servicing->id,
            'qty_requested' => 3.0,
        ]);

        $this->assertDatabaseHas('price_request_lines', [
            'price_request_id' => $pr->id,
            'source_type' => 'blueprint',
            'source_consumable_id' => $this->consumable->id,
            'qty_requested' => 30.0,
        ]);
    }

    public function test_can_add_blueprint_lines_to_quotation(): void
    {
        $quotation = Quotation::create([
            'client_id' => $this->client->id,
            'status' => \App\Modules\Crm\Enums\QuotationStatus::Draft,
            'valid_until' => now()->addDays(30),
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
        ]);

        $res = $this->postJson("/api/crm/quotations/{$quotation->id}/lines/from-blueprint", [
            'blueprint_id' => $this->blueprint->id,
            'selected_option_values' => [$this->val100->id],
            'equipment_qty' => 2.0,
        ]);

        $res->assertOk();

        $this->assertDatabaseHas('quotation_lines', [
            'quotation_id' => $quotation->id,
            'source_type' => 'blueprint',
            'source_blueprint_id' => $this->blueprint->id,
            'source_servicing_id' => $this->servicing->id,
            'qty' => 2.0,
        ]);

        $this->assertDatabaseHas('quotation_lines', [
            'quotation_id' => $quotation->id,
            'source_type' => 'blueprint',
            'source_consumable_id' => $this->consumable->id,
            'qty' => 20.0,
        ]);
    }

    public function test_can_add_blueprint_lines_to_price_list(): void
    {
        $priceList = PriceList::create([
            'name' => 'Standard Client Price List',
            'code' => 'PL-STD-01',
            'currency' => 'TND',
            'type' => \App\Modules\Crm\Enums\PriceListType::Standard,
            'price_computation' => \App\Modules\Crm\Enums\PriceComputationMethod::Fixed,
            'is_active' => false,
            'valid_from' => now()->toDateString(),
        ]);

        $res = $this->postJson("/api/crm/price-lists/{$priceList->id}/lines/from-blueprint", [
            'blueprint_id' => $this->blueprint->id,
            'selected_option_values' => [$this->val100->id],
            'equipment_qty' => 1.0,
        ]);

        $res->assertOk();

        $this->assertDatabaseHas('price_list_lines', [
            'price_list_id' => $priceList->id,
            'source_type' => 'blueprint',
            'source_blueprint_id' => $this->blueprint->id,
            'source_servicing_id' => $this->servicing->id,
        ]);

        $this->assertDatabaseHas('price_list_lines', [
            'price_list_id' => $priceList->id,
            'source_type' => 'blueprint',
            'source_consumable_id' => $this->consumable->id,
        ]);
    }

    public function test_multiple_variants_require_selection(): void
    {
        // Add a second variant to consumable item
        CatalogVariant::create([
            'catalog_item_id' => $this->consumableItem->id,
            'sku' => 'OIL-SYN-20L',
            'name' => '20L Drum',
            'price' => 90.0,
        ]);

        // Attempt resolving without choosing variant
        $builder = app(BlueprintLineBuilderService::class);
        $resolution = $builder->resolve(
            blueprint: $this->blueprint,
            selectedOptionValueIds: [$this->val100->id],
        );

        $this->assertTrue($resolution->hasMissingVariants());
        $this->assertNotEmpty($resolution->missingVariantSelections);

        // When chosen variant provided: succeeds
        $drumVariant = CatalogVariant::where('sku', 'OIL-SYN-20L')->first();
        $resWithChoice = $builder->resolve(
            blueprint: $this->blueprint,
            selectedOptionValueIds: [$this->val100->id],
            variantSelections: [$this->consumable->id => $drumVariant->id],
        );

        $this->assertFalse($resWithChoice->hasMissingVariants());
        $line = collect($resWithChoice->lines)->firstWhere('sourceConsumableId', $this->consumable->id);
        $this->assertEquals($drumVariant->id, $line->variantId);
        $this->assertEquals(90.0, $line->unitPrice);

        // Also test keyed by catalog_item_id as primary key
        $resWithItemKey = $builder->resolve(
            blueprint: $this->blueprint,
            selectedOptionValueIds: [$this->val100->id],
            variantSelections: [$this->consumableItem->id => $drumVariant->id],
        );
        $this->assertFalse($resWithItemKey->hasMissingVariants());
        $lineItemKey = collect($resWithItemKey->lines)->firstWhere('sourceConsumableId', $this->consumable->id);
        $this->assertEquals($drumVariant->id, $lineItemKey->variantId);
    }

    public function test_description_snapshot_includes_blueprint_equipment_identity(): void
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'so_type' => \App\Modules\Crm\Enums\SalesOrderType::Sales,
            'status' => \App\Modules\Crm\Enums\SalesOrderStatus::Draft,
            'currency' => 'TND',
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/lines/from-blueprint", [
            'blueprint_id' => $this->blueprint->id,
            'selected_option_values' => [$this->val100->id],
            'equipment_qty' => 1.0,
        ]);

        $res->assertOk();

        $line = $so->lines()->where('source_consumable_id', $this->consumable->id)->first();
        $this->assertNotNull($line);
        // Description snapshot combines catalog item name and blueprint equipment identity
        $this->assertStringContainsString('Synthetic Lubricant Oil', $line->description_snapshot);
        $this->assertStringContainsString('Industrial Pump X', $line->description_snapshot);
    }

    public function test_invalid_variant_rejected_with_validation_exception(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $otherItem = CatalogItem::create([
            'code' => 'OTHER-ITEM',
            'name' => ['en' => 'Other Product'],
            'item_type' => 'product',
            'category_id' => $this->servicingItem->category_id,
            'uom_id' => $this->servicingItem->uom_id,
            'status' => 'active',
        ]);

        $foreignVariant = CatalogVariant::create([
            'catalog_item_id' => $otherItem->id,
            'sku' => 'OTHER-VAR-01',
            'name' => 'Foreign Variant',
            'price' => 10.0,
        ]);

        // Add second variant to consumable item to trigger multi-variant requirement
        CatalogVariant::create([
            'catalog_item_id' => $this->consumableItem->id,
            'sku' => 'OIL-SYN-60L',
            'name' => '60L Drum',
            'price' => 200.0,
        ]);

        $builder = app(BlueprintLineBuilderService::class);
        $builder->resolve(
            blueprint: $this->blueprint,
            selectedOptionValueIds: [$this->val100->id],
            variantSelections: [$this->consumableItem->id => $foreignVariant->id],
        );
    }
}
