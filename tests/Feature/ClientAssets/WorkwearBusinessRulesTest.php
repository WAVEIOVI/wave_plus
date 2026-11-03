<?php

declare(strict_types=1);

namespace Tests\Feature\ClientAssets;

use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Models\WorkwearModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Client Assets → Workwear business rules:
 * client ownership, optional SO/DN references, option ownership,
 * model client reassignment, notes and employee profile persistence.
 */
class WorkwearBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private Client $clientA;

    private Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        $this->clientA = Client::create(['legal_name' => 'Acme SARL', 'commercial_name' => 'Acme', 'status' => 'active']);
        $this->clientB = Client::create(['legal_name' => 'Beta SA', 'commercial_name' => 'Beta', 'status' => 'active']);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function salesOrderFor(Client $client): string
    {
        $id = (string) Str::ulid();
        DB::table('sales_orders')->insert([
            'id' => $id,
            'reference' => 'SO-TEST-'.Str::upper(Str::random(8)),
            'client_id' => $client->id,
            'payment_terms' => 'immediate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function deliveryNoteFor(Client $client): string
    {
        $id = (string) Str::ulid();
        // The delivery work order is irrelevant here; defer FK checks (test transaction is rolled back).
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('delivery_notes')->insert([
            'id' => $id,
            'dn_number' => 'DN-TEST-'.Str::upper(Str::random(8)),
            'dwo_id' => (string) Str::ulid(),
            'client_id' => $client->id,
            'delivery_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function modelWithOptions(Client $client, string $name = 'Parka'): array
    {
        $res = $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $client->id,
            'name' => $name,
            'status' => 'active',
            'options' => [['name' => 'Size', 'values' => [['value' => 'M'], ['value' => 'L']]]],
        ])->assertCreated();

        $modelId = $res->json('data.id');
        $valueIds = collect($res->json('data.options.0.values'))->pluck('id', 'value')->all();

        return [$modelId, $valueIds];
    }

    // ─── Model reference ─────────────────────────────────────────

    public function test_model_reference_is_built_from_client_reference_not_an_integer_cast_ulid(): void
    {
        $res = $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $this->clientA->id,
            'name' => 'Vest',
        ])->assertCreated();

        $ref = $res->json('data.model_ref');
        $clientRef = strtoupper((string) $this->clientA->fresh()->client_id);

        $this->assertMatchesRegularExpression('/^WM-[A-Z0-9]+-\d{4}-\d{4}$/', $ref);
        $this->assertStringContainsString($clientRef, $ref);
        $this->assertStringNotContainsString('WM-0-', $ref);
    }

    // ─── Notes ───────────────────────────────────────────────────

    public function test_model_notes_persist_on_create_update_and_reload(): void
    {
        $id = $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $this->clientA->id,
            'name' => 'Overall',
            'notes' => 'Initial note',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/client-assets/workwear-models/{$id}")->assertJsonPath('data.notes', 'Initial note');

        $this->putJson("/api/client-assets/workwear-models/{$id}", ['notes' => 'Edited note'])->assertOk();

        $this->getJson("/api/client-assets/workwear-models/{$id}")->assertJsonPath('data.notes', 'Edited note');
        $this->assertDatabaseHas('workwear_models', ['id' => $id, 'notes' => 'Edited note']);
    }

    // ─── Client reassignment ─────────────────────────────────────

    public function test_model_client_cannot_change_when_items_exist(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);
        WorkwearItem::create(['client_id' => $this->clientA->id, 'model_id' => $modelId, 'status' => 'in_stock']);

        $this->putJson("/api/client-assets/workwear-models/{$modelId}", ['client_id' => $this->clientB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('client_id');

        $this->assertDatabaseHas('workwear_models', ['id' => $modelId, 'client_id' => $this->clientA->id]);
    }

    public function test_model_without_items_can_be_reassigned_and_same_client_update_is_allowed(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);

        // Re-sending the same client is a no-op, never an error.
        $this->putJson("/api/client-assets/workwear-models/{$modelId}", ['client_id' => $this->clientA->id, 'name' => 'Renamed'])
            ->assertOk();

        $this->putJson("/api/client-assets/workwear-models/{$modelId}", ['client_id' => $this->clientB->id])->assertOk();
        $this->assertDatabaseHas('workwear_models', ['id' => $modelId, 'client_id' => $this->clientB->id]);
    }

    // ─── Optional SO / DN ────────────────────────────────────────

    public function test_item_can_be_created_without_sales_order_or_delivery_note(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);

        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelId,
        ])->assertCreated()
            ->assertJsonPath('data.sales_order_id', null)
            ->assertJsonPath('data.delivery_note_id', null);
    }

    public function test_item_accepts_same_client_sales_order_and_delivery_note_and_returns_them(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);
        $so = $this->salesOrderFor($this->clientA);
        $dn = $this->deliveryNoteFor($this->clientA);

        $res = $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelId,
            'sales_order_id' => $so,
            'delivery_note_id' => $dn,
        ])->assertCreated();

        $res->assertJsonPath('data.sales_order.id', $so)->assertJsonPath('data.delivery_note.id', $dn);

        $this->getJson('/api/client-assets/workwear-items/'.$res->json('data.id'))
            ->assertJsonPath('data.sales_order.id', $so)
            ->assertJsonPath('data.delivery_note.id', $dn);
    }

    public function test_cross_client_sales_order_and_delivery_note_are_rejected(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);

        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelId,
            'sales_order_id' => $this->salesOrderFor($this->clientB),
        ])->assertStatus(422)->assertJsonValidationErrors('sales_order_id');

        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelId,
            'delivery_note_id' => $this->deliveryNoteFor($this->clientB),
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_note_id');

        $this->assertSame(0, WorkwearItem::count());
    }

    public function test_cross_client_documents_are_rejected_on_update_and_batch(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);
        $item = WorkwearItem::create(['client_id' => $this->clientA->id, 'model_id' => $modelId, 'status' => 'in_stock']);

        $this->putJson("/api/client-assets/workwear-items/{$item->id}", ['sales_order_id' => $this->salesOrderFor($this->clientB)])
            ->assertStatus(422)->assertJsonValidationErrors('sales_order_id');

        $this->postJson('/api/client-assets/workwear-items/batch', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelId,
            'delivery_note_id' => $this->deliveryNoteFor($this->clientB),
            'items' => [['size' => 'M']],
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_note_id');

        $this->assertSame(1, WorkwearItem::count());

        // Same-client document is accepted on update, and can be cleared again.
        $so = $this->salesOrderFor($this->clientA);
        $this->putJson("/api/client-assets/workwear-items/{$item->id}", ['sales_order_id' => $so])
            ->assertOk()->assertJsonPath('data.sales_order.id', $so);
        $this->putJson("/api/client-assets/workwear-items/{$item->id}", ['sales_order_id' => null])
            ->assertOk()->assertJsonPath('data.sales_order_id', null);
    }

    public function test_document_options_endpoint_is_scoped_to_the_selected_client(): void
    {
        $soA = $this->salesOrderFor($this->clientA);
        $this->salesOrderFor($this->clientB);
        $dnA = $this->deliveryNoteFor($this->clientA);
        $this->deliveryNoteFor($this->clientB);

        $res = $this->getJson('/api/client-assets/workwear-document-options?client_id='.$this->clientA->id)->assertOk();

        $this->assertSame([$soA], collect($res->json('data.sales_orders'))->pluck('id')->all());
        $this->assertSame([$dnA], collect($res->json('data.delivery_notes'))->pluck('id')->all());

        $this->getJson('/api/client-assets/workwear-document-options')->assertStatus(422);
    }

    // ─── Option ownership ────────────────────────────────────────

    public function test_option_values_must_belong_to_the_items_model(): void
    {
        [$modelA, $valuesA] = $this->modelWithOptions($this->clientA, 'Model A');
        [$modelA2, $valuesA2] = $this->modelWithOptions($this->clientA, 'Model A2');

        // Value from a different model of the same client
        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelA,
            'option_value_ids' => [$valuesA2['M']],
        ])->assertStatus(422)->assertJsonValidationErrors('option_value_ids');

        // Two values of the same option
        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelA,
            'option_value_ids' => [$valuesA['M'], $valuesA['L']],
        ])->assertStatus(422)->assertJsonValidationErrors('option_value_ids');

        // Valid
        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelA,
            'option_value_ids' => [$valuesA['L']],
        ])->assertCreated()->assertJsonPath('data.item_option_values.0.value', 'L');

        // Batch rows are validated too and nothing is persisted on failure
        $before = WorkwearItem::count();
        $this->postJson('/api/client-assets/workwear-items/batch', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelA,
            'items' => [['option_value_ids' => [$valuesA['M']]], ['option_value_ids' => [$valuesA2['M']]]],
        ])->assertStatus(422);
        $this->assertSame($before, WorkwearItem::count());
    }

    public function test_changing_item_model_clears_stale_option_values_and_client_cannot_change(): void
    {
        [$modelA, $valuesA] = $this->modelWithOptions($this->clientA, 'Model A');
        [$modelA2] = $this->modelWithOptions($this->clientA, 'Model A2');

        $itemId = $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelA,
            'option_value_ids' => [$valuesA['M']],
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/client-assets/workwear-items/{$itemId}", ['client_id' => $this->clientB->id])
            ->assertStatus(422)->assertJsonValidationErrors('client_id');

        $this->putJson("/api/client-assets/workwear-items/{$itemId}", ['model_id' => $modelA2])->assertOk();
        $this->assertDatabaseCount('workwear_item_option_values', 0);
    }

    // ─── Employee profile ────────────────────────────────────────

    public function test_employee_sizes_requirements_notes_and_contacts_save_and_reload(): void
    {
        $id = $this->postJson('/api/client-assets/workwear-employees', [
            'client_id' => $this->clientA->id,
            'first_name' => 'Amel',
            'last_name' => 'Ben Ali',
            'email' => 'amel@acme.test',
            'phone' => '+216 20 000 000',
            'position' => 'Welder',
            'sizes' => ['top' => 'M', 'bottom' => '38', 'shoes' => '40', 'chest' => '92', 'height' => '168'],
            'workwear_requirements' => [['garment_type' => 'coverall', 'quantity' => 2, 'notes' => 'FR rated']],
            'notes' => 'Left-handed',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/client-assets/workwear-employees/{$id}", [
            'sizes' => ['top' => 'L', 'bottom' => '38', 'shoes' => '41', 'waist' => '80'],
            'workwear_requirements' => [
                ['garment_type' => 'coverall', 'quantity' => 3],
                ['garment_type' => 'boots', 'quantity' => 1],
            ],
            'notes' => 'Updated note',
        ])->assertOk();

        $this->getJson("/api/client-assets/workwear-employees/{$id}")
            ->assertOk()
            ->assertJsonPath('data.sizes.top', 'L')
            ->assertJsonPath('data.sizes.waist', '80')
            ->assertJsonPath('data.email', 'amel@acme.test')
            ->assertJsonPath('data.position', 'Welder')
            ->assertJsonPath('data.notes', 'Updated note')
            ->assertJsonPath('data.workwear_requirements.0.quantity', 3)
            ->assertJsonPath('data.workwear_requirements.1.garment_type', 'boots');
    }

    public function test_employee_requirements_are_validated(): void
    {
        $base = ['client_id' => $this->clientA->id, 'first_name' => 'A', 'last_name' => 'B'];

        $this->postJson('/api/client-assets/workwear-employees', $base + [
            'workwear_requirements' => [['garment_type' => 'vest', 'quantity' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('workwear_requirements.0.quantity');

        $this->postJson('/api/client-assets/workwear-employees', $base + [
            'workwear_requirements' => [['quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('workwear_requirements.0.garment_type');
    }

    public function test_employee_with_assignment_history_cannot_change_client(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);
        $item = WorkwearItem::create(['client_id' => $this->clientA->id, 'model_id' => $modelId, 'status' => 'in_stock']);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);

        $this->postJson('/api/client-assets/workwear-items/assign', ['employee_id' => $emp->id, 'item_ids' => [$item->id]])->assertOk();

        $this->putJson("/api/client-assets/workwear-employees/{$emp->id}", ['client_id' => $this->clientB->id])
            ->assertStatus(422)->assertJsonValidationErrors('client_id');
    }

    public function test_assignment_history_separates_active_and_returned_and_exposes_who_and_when(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'G', 'last_name' => 'H']);
        $i1 = WorkwearItem::create(['client_id' => $this->clientA->id, 'model_id' => $modelId, 'status' => 'in_stock']);
        $i2 = WorkwearItem::create(['client_id' => $this->clientA->id, 'model_id' => $modelId, 'status' => 'in_stock']);

        $assign = $this->postJson('/api/client-assets/workwear-items/assign', [
            'employee_id' => $emp->id, 'item_ids' => [$i1->id, $i2->id],
        ])->assertOk();

        $this->postJson('/api/client-assets/assignments/'.$assign->json('data.0.id').'/return', [
            'return_action' => 'restock', 'condition_at_return' => 'good',
        ])->assertOk();

        $res = $this->getJson("/api/client-assets/workwear-employees/{$emp->id}/history")->assertOk();
        $history = collect($res->json('data.history'));

        $this->assertCount(2, $history);
        $this->assertCount(1, $history->whereNull('returned_at'));
        $returned = $history->firstWhere('returned_at', '!=', null);
        $this->assertSame('restock', $returned['return_action']);
        $this->assertNotNull($returned['returned_by_user']);
        $this->assertNotNull($returned['item']['model_name']);

        $this->getJson("/api/client-assets/workwear-employees/{$emp->id}")
            ->assertJsonPath('data.active_assignments_count', 1)
            ->assertJsonPath('data.assignments_count', 2);
    }

    // ─── Model show ──────────────────────────────────────────────

    public function test_model_show_returns_linked_items_for_the_editor(): void
    {
        [$modelId] = $this->modelWithOptions($this->clientA);
        WorkwearItem::create(['client_id' => $this->clientA->id, 'model_id' => $modelId, 'status' => 'in_stock', 'size' => 'L']);

        $res = $this->getJson("/api/client-assets/workwear-models/{$modelId}")->assertOk();

        $this->assertSame(1, $res->json('data.items_count'));
        $this->assertCount(1, $res->json('data.items'));
        $this->assertSame('L', $res->json('data.items.0.size'));

        $this->assertNotNull(WorkwearModel::find($modelId));
    }
}
