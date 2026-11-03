<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Models\PriceRequest;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class CrmSalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private CatalogItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->client = Client::create([
            'legal_name' => 'Northwind Trading',
            'commercial_name' => 'Northwind',
            'client_id' => 'CL-NW-01',
            'status' => 'active',
        ]);

        $uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);
        $category = CatalogCategory::create([
            'name' => ['en' => 'Pumps'],
            'is_active' => true,
        ]);
        $this->item = CatalogItem::create([
            'code' => 'PMP-100',
            'name' => ['en' => 'Industrial Pump'],
            'item_type' => 'product',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'is_stockable' => false,
            'status' => 'active',
        ]);
        CatalogVariant::create([
            'catalog_item_id' => $this->item->id,
            'sku' => 'PMP-100-SKU',
            'price' => 500,
            'is_default' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_access_crm(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/crm/quotations')->assertForbidden();
        $this->getJson('/api/crm/sales-orders')->assertForbidden();
        $this->getJson('/api/crm/contracts')->assertForbidden();
    }

    public function test_reader_cannot_create_quotation(): void
    {
        Permission::findOrCreate('read-CrmSales', 'web');
        $reader = User::factory()->create();
        $reader->givePermissionTo(['read-CrmSales']);
        Sanctum::actingAs($reader);

        $this->getJson('/api/crm/quotations')->assertOk();
        $this->postJson('/api/crm/quotations', $this->quotationPayload())->assertForbidden();
    }

    public function test_price_request_list_searches_and_filters_and_returns_assigned_user(): void
    {
        $otherClient = Client::create([
            'legal_name' => 'Contoso Industries',
            'client_id' => 'CL-CONTOSO',
            'status' => 'active',
        ]);

        PriceRequest::create([
            'reference' => 'PR-SEARCH-01',
            'client_id' => $this->client->id,
            'source' => 'email',
            'received_at' => now(),
            'assigned_to' => $this->user->id,
            'status' => 'pending_internal',
        ]);
        PriceRequest::create([
            'reference' => 'PR-SEARCH-02',
            'client_id' => $otherClient->id,
            'source' => 'email',
            'received_at' => now(),
            'status' => 'responded',
        ]);

        $this->getJson('/api/crm/price-requests?q=Northwind&filter%5Bstatus%5D=pending_internal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'PR-SEARCH-01')
            ->assertJsonPath('data.0.assigned_to_user.name', $this->user->name);
    }

    public function test_quotation_cannot_skip_under_review_when_approval_required(): void
    {
        Sanctum::actingAs($this->user);

        $created = $this->postJson('/api/crm/quotations', $this->quotationPayload(true))->assertCreated();
        $id = $created->json('data.id');
        $this->assertSame('draft', $created->json('data.status'));

        $this->patchJson("/api/crm/quotations/{$id}/status", ['status' => 'accepted'])->assertStatus(422);

        $this->patchJson("/api/crm/quotations/{$id}/status", ['status' => 'sent'])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->patchJson("/api/crm/quotations/{$id}/status", ['status' => 'accepted'])->assertStatus(422);

        $this->patchJson("/api/crm/quotations/{$id}/status", ['status' => 'under_review'])
            ->assertOk();

        $this->patchJson("/api/crm/quotations/{$id}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_accepted_quotation_is_not_editable_and_converts_with_snapshots(): void
    {
        Sanctum::actingAs($this->user);

        $quotation = Quotation::create([
            'client_id' => $this->client->id,
            'valid_until' => now()->addDays(20),
            'currency' => 'TND',
            'payment_terms' => 'net_30',
            'total_ht' => 500,
            'total_tva' => 95,
            'total_ttc' => 595,
            'status' => QuotationStatus::Accepted,
        ]);
        $quotation->lines()->create([
            'catalog_item_id' => $this->item->id,
            'item_type_snapshot' => 'physical_product',
            'qty' => 1,
            'unit_price' => 500,
            'unit_price_after_discount' => 500,
            'discount_pct' => 0,
            'total_ht' => 500,
            'total_tva' => 95,
            'total_ttc' => 595,
            'description_snapshot' => 'Quoted pump name',
            'sku_snapshot' => 'PMP-100-SKU',
            'uom_code_snapshot' => 'PCE',
        ]);

        $this->putJson("/api/crm/quotations/{$quotation->id}", $this->quotationPayload())->assertStatus(422);

        $converted = $this->postJson("/api/crm/sales-orders/convert-quotation/{$quotation->id}")->assertCreated();
        $this->assertSame('Quoted pump name', $converted->json('data.lines.0.description_snapshot'));
        $this->assertSame('PMP-100-SKU', $converted->json('data.lines.0.sku_snapshot'));
        $this->assertSame(QuotationStatus::Converted->value, $quotation->fresh()->status->value);

        $this->postJson("/api/crm/sales-orders/convert-quotation/{$quotation->id}")->assertStatus(422);
    }

    public function test_sales_order_cannot_skip_feasibility_and_cannot_edit_after_submit(): void
    {
        Sanctum::actingAs($this->user);

        $created = $this->postJson('/api/crm/sales-orders', $this->salesOrderPayload())->assertCreated();
        $id = $created->json('data.id');

        $this->postJson("/api/crm/sales-orders/{$id}/accept")->assertStatus(422);

        $this->postJson("/api/crm/sales-orders/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->putJson("/api/crm/sales-orders/{$id}", [
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'qty' => 2,
                'unit_price' => 500,
                'item_type_snapshot' => 'physical_product',
            ]],
        ])->assertStatus(422);

        $this->postJson("/api/crm/sales-orders/{$id}/start-feasibility-review")->assertOk();

        $this->postJson("/api/crm/sales-orders/{$id}/feasibility-review", [
            'decision' => 'approved',
            'credit_check_result' => 'sufficient',
            'stock_check_result' => 'sufficient',
        ])->assertOk();

        $this->deleteJson("/api/crm/sales-orders/{$id}")->assertStatus(422);
    }

    public function test_draft_sales_order_cannot_be_created_as_accepted(): void
    {
        Sanctum::actingAs($this->user);

        $created = $this->postJson('/api/crm/sales-orders', array_merge($this->salesOrderPayload(), [
            'status' => 'accepted',
        ]))->assertCreated();

        $this->assertSame('draft', $created->json('data.status'));
        $this->assertDatabaseHas('sales_orders', [
            'id' => $created->json('data.id'),
            'status' => 'draft',
        ]);
    }

    private function quotationPayload(bool $approvalRequired = false): array
    {
        return [
            'client_id' => $this->client->id,
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency' => 'TND',
            'payment_terms' => 'net_30',
            'approval_required' => $approvalRequired,
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'qty' => 1,
                'unit_price' => 500,
                'unit_price_after_discount' => 500,
                'discount_pct' => 0,
                'tva_rate_pct' => 19,
                'total_ht' => 500,
                'total_tva' => 95,
                'total_ttc' => 595,
                'item_type_snapshot' => 'physical_product',
            ]],
        ];
    }

    private function salesOrderPayload(): array
    {
        return [
            'so_type' => 'sales',
            'creation_path' => 'manual',
            'client_id' => $this->client->id,
            'payment_terms' => 'net_30',
            'currency' => 'TND',
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'qty' => 1,
                'unit_price' => 500,
                'item_type_snapshot' => 'physical_product',
            ]],
        ];
    }
}
