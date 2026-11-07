<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private CatalogItem $item;

    private CatalogVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->supplier = Supplier::create([
            'legal_name' => 'Atlas Supplies',
            'supplier_category' => 'distributor',
            'status' => 'active',
        ]);

        $uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);
        $category = CatalogCategory::create([
            'name' => ['en' => 'Parts'],
            'is_active' => true,
        ]);
        $this->item = CatalogItem::create([
            'code' => 'PART-100',
            'name' => ['en' => 'Filter Cartridge'],
            'item_type' => 'product',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'is_stockable' => true,
            'status' => 'active',
        ]);
        $this->variant = CatalogVariant::create([
            'catalog_item_id' => $this->item->id,
            'sku' => 'PART-100-SKU',
            'price' => 25,
            'is_default' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_access_procurement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/procurement/iprs')->assertForbidden();
        $this->getJson('/api/procurement/pos')->assertForbidden();
        $this->getJson('/api/procurement/grns')->assertForbidden();
    }

    public function test_reader_cannot_approve_ipr(): void
    {
        foreach (['read-SupplyChainProcurement'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $reader = User::factory()->create();
        $reader->givePermissionTo(['read-SupplyChainProcurement']);
        Sanctum::actingAs($reader);

        $this->getJson('/api/procurement/iprs')->assertOk();
        $this->postJson('/api/procurement/iprs', $this->iprPayload())->assertForbidden();
    }

    public function test_ipr_cannot_skip_pending_approval(): void
    {
        Sanctum::actingAs($this->user);

        $created = $this->postJson('/api/procurement/iprs', $this->iprPayload())->assertCreated();
        $id = $created->json('data.id');
        $this->assertSame('draft', $created->json('data.status'));

        $this->patchJson("/api/procurement/iprs/{$id}/status", [
            'status' => 'approved',
        ])->assertStatus(422);

        $this->patchJson("/api/procurement/iprs/{$id}/status", [
            'status' => 'pending_approval',
        ])->assertOk()->assertJsonPath('data.status', 'pending_approval');

        $this->patchJson("/api/procurement/iprs/{$id}/status", [
            'status' => 'approved',
            'approval_note' => 'OK',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_ipr_estimated_unit_cost_is_accepted_on_create_and_update(): void
    {
        $payload = $this->iprPayload();
        $payload['lines'][0]['estimated_unit_cost'] = 25;

        $created = $this->postJson('/api/procurement/iprs', $payload)
            ->assertCreated()
            ->assertJsonPath('data.lines.0.estimated_unit_cost', 25);

        $lineId = $created->json('data.lines.0.id');
        $payload['lines'][0]['id'] = $lineId;
        $payload['lines'][0]['estimated_unit_cost'] = 30;

        $this->putJson('/api/procurement/iprs/'.$created->json('data.id'), $payload)
            ->assertOk()
            ->assertJsonPath('data.lines.0.estimated_unit_cost', 30);
    }

    public function test_approved_ipr_is_not_editable(): void
    {
        Sanctum::actingAs($this->user);
        $id = $this->postJson('/api/procurement/iprs', $this->iprPayload())->json('data.id');
        $this->patchJson("/api/procurement/iprs/{$id}/status", ['status' => 'pending_approval']);
        $this->patchJson("/api/procurement/iprs/{$id}/status", ['status' => 'approved', 'approval_note' => 'OK']);

        $ipr = InternalPurchaseRequest::findOrFail($id);
        $this->putJson("/api/procurement/iprs/{$id}", [
            'trigger_source' => 'manual_user',
            'requested_by' => $this->user->id,
            'urgency' => 'normal',
            'lines' => [[
                'id' => $ipr->lines()->first()->id,
                'catalog_item_id' => $this->item->id,
                'qty_requested' => 9,
            ]],
        ])->assertStatus(422);
    }

    public function test_po_requires_approval_when_flagged_and_cannot_receive_while_draft(): void
    {
        Sanctum::actingAs($this->user);

        $po = $this->postJson('/api/procurement/pos', $this->poPayload([
            'approval_required' => true,
        ]))->assertCreated();

        $poId = $po->json('data.id');
        $this->assertNotEmpty($poId);

        $this->patchJson("/api/procurement/pos/{$poId}/status", [
            'status' => 'approved',
        ])->assertStatus(422);

        $this->patchJson("/api/procurement/pos/{$poId}/status", [
            'status' => 'pending_approval',
        ])->assertOk();

        $this->patchJson("/api/procurement/pos/{$poId}/status", [
            'status' => 'approved',
        ])->assertOk();

        $this->postJson('/api/procurement/grns', $this->grnPayload($poId))->assertStatus(422);

        $this->patchJson("/api/procurement/pos/{$poId}/status", ['status' => 'sent'])->assertOk();

        $this->postJson('/api/procurement/grns', $this->grnPayload($poId))->assertCreated();
    }

    public function test_grn_cannot_over_receive_without_flag(): void
    {
        Sanctum::actingAs($this->user);

        $poId = $this->postJson('/api/procurement/pos', $this->poPayload())->json('data.id');

        $this->patchJson("/api/procurement/pos/{$poId}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/procurement/pos/{$poId}/status", ['status' => 'sent'])->assertOk();

        $po = PurchaseOrder::with('lines')->findOrFail($poId);
        $lineId = $po->lines->first()->id;

        $payload = $this->grnPayload($poId);
        $payload['lines'][0]['po_line_id'] = $lineId;
        $payload['lines'][0]['qty_received'] = 50;
        $payload['lines'][0]['qty_accepted'] = 50;

        $this->postJson('/api/procurement/grns', $payload)->assertStatus(422);

        $payload['allow_over_receive'] = true;
        $this->postJson('/api/procurement/grns', $payload)->assertCreated();
    }

    public function test_rfq_cannot_jump_to_awarded_from_draft(): void
    {
        Sanctum::actingAs($this->user);

        $rfq = $this->postJson('/api/procurement/rfqs', [
            'title' => 'Filters RFQ',
            'response_deadline' => now()->addDays(5)->toDateTimeString(),
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'qty_requested' => 2,
            ]],
            'invitations' => [['supplier_id' => $this->supplier->id]],
        ])->assertCreated();

        $id = $rfq->json('data.id');
        $this->patchJson("/api/procurement/rfqs/{$id}/status", ['status' => 'awarded'])
            ->assertStatus(422);

        $this->patchJson("/api/procurement/rfqs/{$id}/status", ['status' => 'sent'])->assertOk();
    }

    public function test_sent_rfq_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->user);

        $rfq = $this->postJson('/api/procurement/rfqs', [
            'title' => 'Protected RFQ',
            'response_deadline' => now()->addDays(5)->toDateTimeString(),
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'qty_requested' => 2,
            ]],
            'invitations' => [['supplier_id' => $this->supplier->id]],
        ])->assertCreated();

        $id = $rfq->json('data.id');
        $this->patchJson("/api/procurement/rfqs/{$id}/status", ['status' => 'sent'])->assertOk();

        $this->deleteJson("/api/procurement/rfqs/{$id}")
            ->assertStatus(422)
            ->assertJsonPath('message', "This rfq cannot be deleted in status 'sent'.");
    }

    private function iprPayload(): array
    {
        return [
            'trigger_source' => 'manual_user',
            'requested_by' => $this->user->id,
            'urgency' => 'normal',
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'variant_id' => $this->variant->id,
                'qty_requested' => 4,
            ]],
        ];
    }

    private function poPayload(array $overrides = []): array
    {
        return array_merge([
            'creation_path' => 'manual',
            'supplier_id' => $this->supplier->id,
            'payment_terms' => 'net_30',
            'currency' => 'TND',
            'approval_required' => false,
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'variant_id' => $this->variant->id,
                'qty_ordered' => 2,
                'unit_price_ht' => 10,
                'tax_rate_pct' => 19,
            ]],
        ], $overrides);
    }

    private function grnPayload(string $poId): array
    {
        $po = PurchaseOrder::with('lines')->findOrFail($poId);

        return [
            'po_id' => $poId,
            'supplier_id' => $this->supplier->id,
            'received_by' => $this->user->id,
            'received_at' => now()->toDateTimeString(),
            'lines' => [[
                'po_line_id' => $po->lines->first()->id,
                'catalog_item_id' => $this->item->id,
                'catalog_variant_id' => $this->variant->id,
                'qty_received' => 1,
                'qty_accepted' => 1,
                'unit_cost_ht' => 10,
            ]],
        ];
    }
}
