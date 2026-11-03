<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Enums\ContractStatus;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoCreationPath;
use App\Modules\Crm\Enums\SoFulfillmentMode;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class SalesOrderWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private BpAddress $address;
    private ClientSite $site;
    private CatalogItem $catalogItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Acme Corporation',
            'commercial_name' => 'Acme',
            'client_id' => 'CL-ACME-01',
            'status' => 'active',
        ]);

        $this->address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $this->client->id,
            'type' => 'shipping',
            'address_street' => '123 Industrial Ave',
            'city' => 'Tunis',
            'postal_code' => '1000',
            'country' => 'Tunisia',
            'is_primary' => true,
        ]);

        $this->site = ClientSite::create([
            'client_id' => $this->client->id,
            'bp_address_id' => $this->address->id,
            'name' => 'Main Production Facility',
            'site_code' => 'ACME-MAIN',
        ]);

        $cat = CatalogCategory::create([
            'name' => 'General',
            'code' => 'CAT-GEN',
        ]);

        $uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);

        $this->catalogItem = CatalogItem::create([
            'code' => 'ITEM-001',
            'name' => ['en' => 'Safety Valve'],
            'item_type' => 'product',
            'category_id' => $cat->id,
            'base_price' => 150.0,
            'uom_id' => $uom->id,
            'is_sellable' => true,
            'status' => 'active',
        ]);
    }

    public function test_wizard_creates_so_with_client(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'requested_fulfillment_mode' => 'own_delivery',
            'requested_delivery_date_from' => now()->addDays(2)->toDateString(),
            'requested_delivery_date_to' => now()->addDays(5)->toDateString(),
            'locations' => [
                [
                    'bp_address_id' => $this->address->id,
                    'purpose' => 'delivery',
                    'is_primary' => true,
                ],
            ],
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertCreated();
        $soId = $res->json('data.id');

        $this->assertDatabaseHas('sales_orders', [
            'id' => $soId,
            'client_id' => $this->client->id,
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'requested_fulfillment_mode' => 'own_delivery',
            'status' => 'draft',
        ]);

        $so = SalesOrder::find($soId);
        $this->assertNotNull($so->starter_locked_at);
        $this->assertTrue($so->isStarterLocked());
        $this->assertCount(1, $so->locations);
    }

    public function test_wizard_quotation_from_wrong_client_rejected(): void
    {
        $otherClient = Client::create([
            'legal_name' => 'Other Corp',
            'commercial_name' => 'Other',
            'client_id' => 'CL-OTHER-01',
            'status' => 'active',
        ]);

        $quotation = Quotation::create([
            'client_id' => $otherClient->id,
            'currency' => 'TND',
            'status' => QuotationStatus::Accepted,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
            'valid_until' => now()->addDays(30),
        ]);

        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'sales',
            'creation_path' => 'quotation',
            'quotation_id' => $quotation->id,
            'requested_fulfillment_mode' => 'own_delivery',
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['quotation_id']);
    }

    public function test_wizard_contract_from_wrong_client_rejected(): void
    {
        $otherClient = Client::create([
            'legal_name' => 'Other Corp',
            'commercial_name' => 'Other',
            'client_id' => 'CL-OTHER-02',
            'status' => 'active',
        ]);

        $contract = Contract::create([
            'client_id' => $otherClient->id,
            'title' => 'Framework Contract',
            'type' => 'framework',
            'currency' => 'TND',
            'status' => ContractStatus::Active,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);

        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'service',
            'creation_path' => 'contract_call_off',
            'contract_id' => $contract->id,
            'requested_fulfillment_mode' => 'field_service',
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['contract_id']);
    }

    public function test_wizard_imports_quotation_lines(): void
    {
        $quotation = Quotation::create([
            'client_id' => $this->client->id,
            'currency' => 'TND',
            'status' => QuotationStatus::Accepted,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
            'valid_until' => now()->addDays(30),
            'total_ht' => 300.0,
            'total_tva' => 57.0,
            'total_ttc' => 357.0,
        ]);

        $quotation->lines()->create([
            'catalog_item_id' => $this->catalogItem->id,
            'qty' => 2,
            'unit_price' => 150.0,
            'unit_price_after_discount' => 150.0,
            'discount_pct' => 0,
            'total_ht' => 300.0,
            'total_tva' => 57.0,
            'total_ttc' => 357.0,
            'item_type_snapshot' => 'product',
        ]);

        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'sales',
            'creation_path' => 'quotation',
            'quotation_id' => $quotation->id,
            'requested_fulfillment_mode' => 'own_delivery',
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertCreated();
        $soId = $res->json('data.id');

        $so = SalesOrder::find($soId);
        $this->assertCount(1, $so->lines);
        $this->assertSame('physical_product', $so->lines->first()->item_type_snapshot->value);
        $this->assertEquals(300.0, (float) $so->total_ht);
        $this->assertEquals(357.0, (float) $so->total_ttc);
        $this->assertEquals($soId, $quotation->fresh()->converted_to_so_id);
    }

    public function test_wizard_creates_contract_call_off(): void
    {
        $contract = Contract::create([
            'client_id' => $this->client->id,
            'title' => 'Maintenance Agreement',
            'type' => 'framework',
            'currency' => 'TND',
            'status' => ContractStatus::Active,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);

        $contract->lines()->create([
            'catalog_item_id' => $this->catalogItem->id,
            'contracted_qty' => 5,
            'contracted_unit_price' => 120.0,
            'discount_pct' => 0,
        ]);

        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'service',
            'creation_path' => 'contract_call_off',
            'contract_id' => $contract->id,
            'requested_fulfillment_mode' => 'field_service',
            'locations' => [
                [
                    'bp_address_id' => $this->address->id,
                    'client_site_id' => $this->site->id,
                    'purpose' => 'service',
                    'is_primary' => true,
                ],
            ],
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertCreated();
        $soId = $res->json('data.id');

        $so = SalesOrder::find($soId);
        $this->assertEquals('service', $so->so_type->value);
        $this->assertEquals('contract_call_off', $so->creation_path->value);
        $this->assertCount(1, $so->lines);
        $this->assertEquals(600.0, (float) $so->total_ht); // 5 * 120
    }

    public function test_wizard_invalid_fulfillment_mode_rejected(): void
    {
        // 'field_service' is invalid for 'sales' orders
        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'requested_fulfillment_mode' => 'field_service',
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['requested_fulfillment_mode']);
    }

    public function test_wizard_date_interval_validation(): void
    {
        // 'to' before 'from'
        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'requested_fulfillment_mode' => 'own_delivery',
            'requested_delivery_date_from' => '2026-09-25',
            'requested_delivery_date_to' => '2026-09-20', // Invalid: before from
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['requested_delivery_date_to']);
    }

    public function test_wizard_foreign_address_rejected(): void
    {
        $otherClient = Client::create([
            'legal_name' => 'Other Corp',
            'commercial_name' => 'Other',
            'client_id' => 'CL-OTHER-03',
            'status' => 'active',
        ]);

        $foreignAddress = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $otherClient->id,
            'address_street' => '999 Foreign Road',
            'city' => 'Sfax',
        ]);

        $payload = [
            'client_id' => $this->client->id,
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'requested_fulfillment_mode' => 'own_delivery',
            'locations' => [
                [
                    'bp_address_id' => $foreignAddress->id,
                    'purpose' => 'delivery',
                ],
            ],
        ];

        $res = $this->postJson('/api/crm/sales-orders/wizard', $payload);

        $res->assertUnprocessable();
    }
}
