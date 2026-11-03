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
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class SalesOrderLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private CatalogItem $item;
    private BpAddress $address1;
    private BpAddress $address2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Apex Industries',
            'commercial_name' => 'Apex',
            'client_id' => 'CL-APEX-01',
            'status' => 'active',
        ]);

        $uom = UnitOfMeasure::create([
            'code' => 'UNIT',
            'name' => ['en' => 'Unit'],
            'type' => 'count',
        ]);

        $category = CatalogCategory::create([
            'name' => 'Industrial Items',
            'code' => 'CAT-IND',
        ]);

        $this->item = CatalogItem::create([
            'code' => 'IND-001',
            'name' => ['en' => 'Industrial Sensor'],
            'item_type' => 'product',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'base_price' => 150.0,
            'is_stockable' => true,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        CatalogVariant::create([
            'catalog_item_id' => $this->item->id,
            'sku' => 'IND-001-STD',
            'name' => 'Standard Sensor',
            'price' => 150.0,
            'is_default' => true,
        ]);

        $this->address1 = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->client->id,
            'type' => 'operational_site',
            'address_street' => ['en' => '123 Industrial Ave'],
            'city' => ['en' => 'Tunis'],
            'country' => 'Tunisia',
        ]);

        $this->address2 = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->client->id,
            'type' => 'warehouse',
            'address_street' => ['en' => '456 Port Road'],
            'city' => ['en' => 'Radès'],
            'country' => 'Tunisia',
        ]);
    }

    public function test_can_create_sales_order_with_multiple_locations(): void
    {
        $payload = [
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'locations' => [
                [
                    'bp_address_id' => $this->address1->id,
                    'purpose' => 'delivery',
                    'is_primary' => true,
                ],
                [
                    'bp_address_id' => $this->address2->id,
                    'purpose' => 'billing',
                    'is_primary' => false,
                ],
            ],
            'lines' => [
                [
                    'catalog_item_id' => $this->item->id,
                    'qty' => 5,
                    'unit_price' => 150.0,
                    'item_type_snapshot' => 'physical_product',
                ],
            ],
        ];

        $res = $this->postJson('/api/crm/sales-orders', $payload);
        $res->assertCreated();

        $soId = $res->json('data.id');

        $this->assertDatabaseHas('sales_order_locations', [
            'sales_order_id' => $soId,
            'bp_address_id' => $this->address1->id,
            'purpose' => 'delivery',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('sales_order_locations', [
            'sales_order_id' => $soId,
            'bp_address_id' => $this->address2->id,
            'purpose' => 'billing',
            'is_primary' => false,
        ]);
    }

    public function test_can_add_and_remove_locations_via_api(): void
    {
        $so = SalesOrder::create([
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'immediate',
            'currency' => 'TND',
        ]);

        // Add primary delivery location
        $addRes = $this->postJson("/api/crm/sales-orders/{$so->id}/locations", [
            'bp_address_id' => $this->address1->id,
            'purpose' => 'delivery',
            'is_primary' => true,
        ]);
        $addRes->assertCreated();
        $locationId = $addRes->json('data.id');

        // Add secondary service location
        $this->postJson("/api/crm/sales-orders/{$so->id}/locations", [
            'bp_address_id' => $this->address2->id,
            'purpose' => 'service',
            'is_primary' => false,
        ])->assertCreated();

        // Get locations list
        $listRes = $this->getJson("/api/crm/sales-orders/{$so->id}/locations");
        $listRes->assertOk();
        $this->assertCount(2, $listRes->json('data'));

        // Delete primary location
        $delRes = $this->deleteJson("/api/crm/sales-orders/{$so->id}/locations/{$locationId}");
        $delRes->assertNoContent();

        $this->assertDatabaseMissing('sales_order_locations', ['id' => $locationId]);
    }

    public function test_invalid_address_is_rejected(): void
    {
        $so = SalesOrder::create([
            'so_type' => 'sales',
            'client_id' => $this->client->id,
            'payment_terms' => 'immediate',
            'currency' => 'TND',
        ]);

        $this->postJson("/api/crm/sales-orders/{$so->id}/locations", [
            'bp_address_id' => 999999, // Non-existent
            'purpose' => 'delivery',
        ])->assertStatus(422);
    }

    public function test_quotation_conversion_inherits_location_and_sets_canonical_creation_path(): void
    {
        $quotation = \App\Modules\Crm\Models\Quotation::create([
            'client_id' => $this->client->id,
            'site_id' => $this->address1->id,
            'status' => \App\Modules\Crm\Enums\QuotationStatus::Accepted,
            'payment_terms' => 'net_30',
            'valid_until' => now()->addDays(30),
        ]);

        $quotation->lines()->create([
            'catalog_item_id' => $this->item->id,
            'item_type_snapshot' => 'physical_product',
            'qty' => 1,
            'unit_price' => 150.0,
            'unit_price_after_discount' => 150.0,
            'discount_pct' => 0,
            'total_ht' => 150.0,
            'total_tva' => 28.5,
            'total_ttc' => 178.5,
        ]);

        $res = $this->postJson("/api/crm/sales-orders/convert-quotation/{$quotation->id}");
        $res->assertCreated();

        $soId = $res->json('data.id');
        $this->assertSame('quotation', $res->json('data.creation_path'));

        // Verify sales_order_locations inherited the quotation's site_id
        $this->assertDatabaseHas('sales_order_locations', [
            'sales_order_id' => $soId,
            'bp_address_id' => $this->address1->id,
            'purpose' => 'delivery',
            'is_primary' => true,
        ]);
    }

    public function test_contract_call_off_sets_canonical_creation_path(): void
    {
        $contract = \App\Modules\Crm\Models\Contract::create([
            'client_id' => $this->client->id,
            'title' => 'MSA Contract',
            'contract_number' => 'CTR-2026-001',
            'type' => \App\Modules\Crm\Enums\ContractType::Msa,
            'status' => \App\Modules\Crm\Enums\ContractStatus::Active,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
            'start_date' => now()->subDay(),
            'end_date' => now()->addYear(),
        ]);

        $res = $this->postJson("/api/crm/sales-orders/convert-contract/{$contract->id}");
        $res->assertCreated();

        $this->assertSame('contract_call_off', $res->json('data.creation_path'));
    }
}
