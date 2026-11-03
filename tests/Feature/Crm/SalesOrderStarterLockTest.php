<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoCreationPath;
use App\Modules\Crm\Enums\SoFulfillmentMode;
use App\Modules\Crm\Models\GcsDocument;
use App\Modules\Crm\Models\LsaDocument;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\ServiceWorkOrder;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class SalesOrderStarterLockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private CatalogItem $goodItem;
    private CatalogItem $serviceItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Lock Test Client',
            'commercial_name' => 'LTC',
            'client_id' => 'CL-LTC-01',
            'status' => 'active',
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

        $this->goodItem = CatalogItem::create([
            'code' => 'ITEM-GD',
            'name' => ['en' => 'Extinguisher 6kg'],
            'item_type' => 'product',
            'category_id' => $cat->id,
            'uom_id' => $uom->id,
            'base_price' => 80.0,
            'status' => 'active',
            'is_sellable' => true,
        ]);

        $this->serviceItem = CatalogItem::create([
            'code' => 'ITEM-SV',
            'name' => ['en' => 'Inspection Service'],
            'item_type' => 'eq_servicing',
            'category_id' => $cat->id,
            'uom_id' => $uom->id,
            'base_price' => 45.0,
            'status' => 'active',
            'is_sellable' => true,
        ]);
    }

    public function test_starter_fields_locked_after_first_save(): void
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'so_type' => SalesOrderType::Sales,
            'creation_path' => SoCreationPath::Manual,
            'requested_fulfillment_mode' => SoFulfillmentMode::OwnDelivery->value,
            'requested_delivery_date_from' => '2026-10-01',
            'requested_delivery_date_to' => '2026-10-05',
            'status' => SalesOrderStatus::Draft,
            'starter_locked_at' => now(),
            'currency' => 'TND',
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
        ]);

        $so->lines()->create([
            'catalog_item_id' => $this->goodItem->id,
            'qty' => 1,
            'unit_price' => 80.0,
            'item_type_snapshot' => 'product',
            'total_ht' => 80.0,
            'total_tva' => 15.2,
            'total_ttc' => 95.2,
        ]);

        $otherClient = Client::create([
            'legal_name' => 'Intruder Corp',
            'commercial_name' => 'Intruder',
            'client_id' => 'CL-INT-01',
            'status' => 'active',
        ]);

        // Attempt to change client_id on locked SO
        $res = $this->putJson("/api/crm/sales-orders/{$so->id}", [
            'client_id' => $otherClient->id,
        ]);

        $res->assertUnprocessable();

        // Attempt to change requested_fulfillment_mode
        $res2 = $this->putJson("/api/crm/sales-orders/{$so->id}", [
            'requested_fulfillment_mode' => 'customer_pickup',
        ]);

        $res2->assertUnprocessable();
    }

    public function test_lsa_gcs_cannot_be_manually_attached(): void
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'so_type' => SalesOrderType::Sales,
            'creation_path' => SoCreationPath::Manual,
            'requested_fulfillment_mode' => SoFulfillmentMode::OwnDelivery->value,
            'status' => SalesOrderStatus::Draft,
            'starter_locked_at' => now(),
            'currency' => 'TND',
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
        ]);

        $res = $this->putJson("/api/crm/sales-orders/{$so->id}", [
            'lsa_id' => 'fake-lsa-id',
            'gcs_id' => 'fake-gcs-id',
        ]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['lsa_id', 'gcs_id']);
    }

    public function test_approval_generates_gcs_for_sales_so(): void
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'so_type' => SalesOrderType::Sales,
            'creation_path' => SoCreationPath::Manual,
            'requested_fulfillment_mode' => SoFulfillmentMode::OwnDelivery->value,
            'requested_delivery_date_from' => '2026-10-01',
            'requested_delivery_date_to' => '2026-10-05',
            'status' => SalesOrderStatus::UnderReview,
            'feasibility_status' => FeasibilityStatus::Approved,
            'starter_locked_at' => now(),
            'currency' => 'TND',
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
        ]);

        $so->lines()->create([
            'catalog_item_id' => $this->goodItem->id,
            'qty' => 3,
            'unit_price' => 80.0,
            'item_type_snapshot' => 'product',
            'total_ht' => 240.0,
            'total_tva' => 45.6,
            'total_ttc' => 285.6,
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/accept");
        $res->assertOk();

        $so->refresh();
        $this->assertEquals(SalesOrderStatus::Accepted, $so->status);
        $this->assertNotNull($so->gcs_id);
        $this->assertNull($so->lsa_id);

        $gcs = GcsDocument::find($so->gcs_id);
        $this->assertNotNull($gcs);
        $this->assertEquals($so->id, $gcs->sales_order_id);

        // Verify commitment document endpoint returns GCS
        $commitRes = $this->getJson("/api/crm/sales-orders/{$so->id}/commitment-document");
        $commitRes->assertOk();
        $this->assertEquals('gcs', $commitRes->json('data.type'));
    }

    public function test_approval_generates_lsa_for_service_so(): void
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'so_type' => SalesOrderType::Service,
            'creation_path' => SoCreationPath::Manual,
            'requested_fulfillment_mode' => SoFulfillmentMode::FieldService->value,
            'requested_delivery_date_from' => '2026-10-10',
            'requested_delivery_date_to' => '2026-10-15',
            'status' => SalesOrderStatus::UnderReview,
            'feasibility_status' => FeasibilityStatus::Approved,
            'starter_locked_at' => now(),
            'currency' => 'TND',
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
        ]);

        $so->lines()->create([
            'catalog_item_id' => $this->serviceItem->id,
            'qty' => 1,
            'unit_price' => 45.0,
            'item_type_snapshot' => 'service',
            'total_ht' => 45.0,
            'total_tva' => 8.55,
            'total_ttc' => 53.55,
        ]);

        $res = $this->postJson("/api/crm/sales-orders/{$so->id}/accept");
        $res->assertOk();

        $so->refresh();
        $this->assertEquals(SalesOrderStatus::Accepted, $so->status);
        $this->assertNotNull($so->lsa_id);
        $this->assertNull($so->gcs_id);

        $lsa = LsaDocument::find($so->lsa_id);
        $this->assertNotNull($lsa);
        $this->assertEquals($so->id, $lsa->sales_order_id);

        // Verify commitment document endpoint returns LSA
        $commitRes = $this->getJson("/api/crm/sales-orders/{$so->id}/commitment-document");
        $commitRes->assertOk();
        $this->assertEquals('lsa', $commitRes->json('data.type'));
    }

    public function test_approval_does_not_generate_dwo_or_swo(): void
    {
        $so = SalesOrder::create([
            'client_id' => $this->client->id,
            'so_type' => SalesOrderType::Sales,
            'creation_path' => SoCreationPath::Manual,
            'requested_fulfillment_mode' => SoFulfillmentMode::OwnDelivery->value,
            'status' => SalesOrderStatus::UnderReview,
            'feasibility_status' => FeasibilityStatus::Approved,
            'starter_locked_at' => now(),
            'currency' => 'TND',
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Net30,
        ]);

        $so->lines()->create([
            'catalog_item_id' => $this->goodItem->id,
            'qty' => 1,
            'unit_price' => 80.0,
            'item_type_snapshot' => 'product',
            'total_ht' => 80.0,
            'total_tva' => 15.2,
            'total_ttc' => 95.2,
        ]);

        $this->postJson("/api/crm/sales-orders/{$so->id}/accept")->assertOk();

        // Downstream work orders must NOT be automatically generated upon SO acceptance
        $this->assertEquals(0, DeliveryWorkOrder::where('sales_order_id', $so->id)->count());
        $this->assertEquals(0, ServiceWorkOrder::where('sales_order_id', $so->id)->count());
    }
}
