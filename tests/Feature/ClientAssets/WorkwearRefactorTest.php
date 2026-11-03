<?php

declare(strict_types=1);

namespace Tests\Feature\ClientAssets;

use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogProduct;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Support\WorkwearStudioTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Client Assets — Workwear refactor: model studio (illustration + placements +
 * revisions), client employees (measurements, model entitlements vs issuance)
 * and workwear items (SKU / colour / supplier / stock / photos).
 */
class WorkwearRefactorTest extends TestCase
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

    // ─── helpers ────────────────────────────────────────────────

    private function createModel(Client $client, array $extra = []): string
    {
        return $this->postJson('/api/client-assets/workwear-models', $extra + [
            'client_id' => $client->id,
            'name' => 'Industrial Coverall',
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }

    private function placement(array $overrides = []): array
    {
        return $overrides + [
            'view' => 'front',
            'type' => 'logo',
            'label' => 'Chest logo',
            'zone' => 'chest_left',
            'method' => 'embroidery',
            'width_mm' => 90,
            'height_mm' => 45,
            'offset_x_mm' => 12,
            'offset_y_mm' => 8,
        ];
    }

    private function item(string $modelId, Client $client, array $extra = []): WorkwearItem
    {
        return WorkwearItem::create($extra + ['client_id' => $client->id, 'model_id' => $modelId, 'status' => 'in_stock']);
    }

    // ─── Models: studio ─────────────────────────────────────────

    public function test_model_studio_fields_and_placements_persist_and_sync(): void
    {
        $id = $this->createModel($this->clientA, [
            'silhouette' => 'coverall',
            'size_range' => ['S', 'M', 'L'],
            'colors' => ['navy', 'orange'],
            'technical_specs' => ['fastening' => 'YKK two-way zip', 'care' => 'ISO 15797'],
            'placements' => [
                $this->placement(),
                $this->placement(['view' => 'back', 'type' => 'text', 'label' => 'Company', 'zone' => 'back_upper', 'text' => 'ACME']),
            ],
        ]);

        $show = $this->getJson("/api/client-assets/workwear-models/{$id}")->assertOk();
        $show->assertJsonPath('data.silhouette', 'coverall')
            ->assertJsonPath('data.size_range', ['S', 'M', 'L'])
            ->assertJsonPath('data.technical_specs.fastening', 'YKK two-way zip')
            ->assertJsonCount(2, 'data.placements')
            ->assertJsonPath('data.placements.0.view', 'back')
            ->assertJsonPath('data.placements.1.zone', 'chest_left');

        $keep = collect($show->json('data.placements'))->firstWhere('view', 'front');

        // Update: keep + edit one placement, add a reflective band, drop the back text.
        $this->putJson("/api/client-assets/workwear-models/{$id}", [
            'placements' => [
                array_merge($keep, ['offset_x_mm' => 20]),
                $this->placement(['type' => 'reflective_band', 'label' => 'Waist band', 'zone' => 'waist', 'color' => 'silver']),
            ],
        ])->assertOk()->assertJsonCount(2, 'data.placements');

        $placements = collect($this->getJson("/api/client-assets/workwear-models/{$id}")->json('data.placements'));
        $this->assertSame($keep['id'], $placements->firstWhere('zone', 'chest_left')['id']);
        $this->assertEquals(20, $placements->firstWhere('zone', 'chest_left')['offset_x_mm']);
        $this->assertNull($placements->firstWhere('view', 'back'));

        // Omitting `placements` leaves them untouched; an empty array clears them.
        $this->putJson("/api/client-assets/workwear-models/{$id}", ['name' => 'Renamed'])->assertOk()->assertJsonCount(2, 'data.placements');
        $this->putJson("/api/client-assets/workwear-models/{$id}", ['placements' => []])->assertOk()->assertJsonCount(0, 'data.placements');
    }

    public function test_model_studio_payload_is_validated(): void
    {
        $base = ['client_id' => $this->clientA->id, 'name' => 'M'];

        $this->postJson('/api/client-assets/workwear-models', $base + ['silhouette' => 'cape'])
            ->assertStatus(422)->assertJsonValidationErrors('silhouette');

        $this->postJson('/api/client-assets/workwear-models', $base + ['placements' => [$this->placement(['zone' => 'forehead'])]])
            ->assertStatus(422)->assertJsonValidationErrors('placements.0.zone');

        $this->postJson('/api/client-assets/workwear-models', $base + ['placements' => [$this->placement(['view' => 'side'])]])
            ->assertStatus(422)->assertJsonValidationErrors('placements.0.view');

        $this->postJson('/api/client-assets/workwear-models', $base + ['size_range' => ['M', 'M']])
            ->assertStatus(422)->assertJsonValidationErrors('size_range.0');

        // Artwork must already belong to the model.
        $this->postJson('/api/client-assets/workwear-models', $base + ['placements' => [$this->placement(['media_id' => 99])]])
            ->assertStatus(422)->assertJsonValidationErrors('placements.0.media_id');
    }

    public function test_placement_artwork_must_belong_to_the_model_and_is_detached_on_delete(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $id = $this->createModel($this->clientA);
        $other = $this->createModel($this->clientA, ['name' => 'Other']);

        $mediaId = $this->postJson("/api/client-assets/workwear-models/{$id}/media", [
            'file' => UploadedFile::fake()->image('logo.png', 300, 200),
            'collection' => 'logos',
        ])->assertCreated()->json('data.logos.0.id');

        $this->putJson("/api/client-assets/workwear-models/{$other}", ['placements' => [$this->placement(['media_id' => $mediaId])]])
            ->assertStatus(422)->assertJsonValidationErrors('placements.0.media_id');

        $this->putJson("/api/client-assets/workwear-models/{$id}", ['placements' => [$this->placement(['media_id' => $mediaId])]])
            ->assertOk()->assertJsonPath('data.placements.0.media_id', $mediaId);

        $this->deleteJson("/api/client-assets/workwear-models/{$id}/media/{$mediaId}")->assertOk();

        $this->getJson("/api/client-assets/workwear-models/{$id}")
            ->assertJsonCount(1, 'data.placements')
            ->assertJsonPath('data.placements.0.media_id', null);
    }

    public function test_revisions_snapshot_placements_and_can_be_created_on_update(): void
    {
        $id = $this->createModel($this->clientA, ['silhouette' => 'jacket', 'placements' => [$this->placement()]]);

        $this->putJson("/api/client-assets/workwear-models/{$id}", [
            'fabric' => 'Twill 280gsm',
            'change_summary' => 'Fabric upgrade',
        ])->assertOk()
            ->assertJsonPath('data.versions.0.version_number', 1)
            ->assertJsonPath('data.versions.0.change_summary', 'Fabric upgrade')
            ->assertJsonPath('data.versions.0.snapshot.fabric', 'Twill 280gsm')
            ->assertJsonCount(1, 'data.versions.0.snapshot.placements');

        $this->postJson("/api/client-assets/workwear-models/{$id}/version", ['change_summary' => 'Manual'])
            ->assertCreated()->assertJsonPath('data.version_number', 2);

        // No change_summary → no implicit revision.
        $this->putJson("/api/client-assets/workwear-models/{$id}", ['name' => 'X'])->assertOk();
        $this->assertSame(2, $this->getJson("/api/client-assets/workwear-models/{$id}")->json('data.versions.0.version_number'));
    }

    public function test_model_list_exposes_stock_counts_and_placements_for_previews(): void
    {
        $id = $this->createModel($this->clientA, ['placements' => [$this->placement()]]);
        $this->item($id, $this->clientA);
        $this->item($id, $this->clientA, ['status' => 'retired']);

        $row = $this->getJson('/api/client-assets/workwear-models?filter[client_id]='.$this->clientA->id)->assertOk()->json('data.0');
        $this->assertSame(2, $row['items_count']);
        $this->assertSame(1, $row['available_items_count']);
        $this->assertCount(1, $row['placements']);
    }

    // ─── Client employees ───────────────────────────────────────

    public function test_employee_measurements_persist_and_are_validated(): void
    {
        $res = $this->postJson('/api/client-assets/workwear-employees', [
            'client_id' => $this->clientA->id,
            'first_name' => 'Sarah',
            'last_name' => 'Martin',
            'sizes' => ['top' => 'M'],
            'measurements' => ['height' => 172.5, 'chest' => 94, 'inseam' => 80],
        ])->assertCreated();

        $this->assertEquals(94, $res->json('data.measurements.chest'));
        $this->assertSame('M', $res->json('data.sizes.top'));

        $this->putJson('/api/client-assets/workwear-employees/'.$res->json('data.id'), ['measurements' => ['chest' => -4]])
            ->assertStatus(422)->assertJsonValidationErrors('measurements.chest');
    }

    public function test_model_assignment_is_an_entitlement_distinct_from_issuance(): void
    {
        $modelId = $this->createModel($this->clientA);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);

        // Assign the model (entitlement) — no item exists, nothing is issued.
        $this->putJson("/api/client-assets/workwear-employees/{$emp->id}", [
            'model_assignments' => [['model_id' => $modelId, 'quantity' => 2, 'size' => 'L']],
        ])->assertOk()
            ->assertJsonCount(1, 'data.model_assignments')
            ->assertJsonPath('data.model_assignments.0.quantity', 2)
            ->assertJsonPath('data.model_assignments.0.issued_count', 0)
            ->assertJsonPath('data.model_assignments.0.is_active', true);

        // Issue one physical item → issuance progress changes, entitlement does not.
        $item = $this->item($modelId, $this->clientA, ['size' => 'L']);
        $this->postJson('/api/client-assets/workwear-items/assign', ['employee_id' => $emp->id, 'item_ids' => [$item->id]])->assertOk();

        $show = $this->getJson("/api/client-assets/workwear-employees/{$emp->id}")->assertOk();
        $show->assertJsonPath('data.model_assignments.0.quantity', 2)
            ->assertJsonPath('data.model_assignments.0.issued_count', 1)
            ->assertJsonPath('data.active_model_assignments_count', 1)
            ->assertJsonPath('data.active_assignments_count', 1);

        $this->assertDatabaseCount('workwear_employee_models', 1);
    }

    public function test_issuing_does_not_require_a_model_entitlement(): void
    {
        $modelId = $this->createModel($this->clientA);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);
        $item = $this->item($modelId, $this->clientA);

        $this->postJson('/api/client-assets/workwear-items/assign', ['employee_id' => $emp->id, 'item_ids' => [$item->id]])->assertOk();
    }

    public function test_ended_model_assignments_are_kept_as_history_and_can_be_reassigned(): void
    {
        $m1 = $this->createModel($this->clientA, ['name' => 'One']);
        $m2 = $this->createModel($this->clientA, ['name' => 'Two']);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);
        $url = "/api/client-assets/workwear-employees/{$emp->id}";

        $this->putJson($url, ['model_assignments' => [['model_id' => $m1, 'quantity' => 1], ['model_id' => $m2, 'quantity' => 1]]])->assertOk();
        $this->putJson($url, ['model_assignments' => [['model_id' => $m2, 'quantity' => 3]]])->assertOk();

        $rows = collect($this->getJson($url)->json('data.model_assignments'));
        $this->assertCount(2, $rows);
        $this->assertFalse($rows->firstWhere('model_id', $m1)['is_active']);
        $this->assertNotNull($rows->firstWhere('model_id', $m1)['ended_at']);
        $this->assertSame(3, $rows->firstWhere('model_id', $m2)['quantity']);

        // Re-assigning an ended model creates a fresh active entitlement, history is retained.
        $this->putJson($url, ['model_assignments' => [['model_id' => $m1, 'quantity' => 1], ['model_id' => $m2, 'quantity' => 3]]])->assertOk();
        $this->assertDatabaseCount('workwear_employee_models', 3);

        // Omitting the key leaves entitlements untouched; [] ends them all.
        $this->putJson($url, ['notes' => 'x'])->assertOk()->assertJsonPath('data.active_model_assignments_count', 2);
        $this->putJson($url, ['model_assignments' => []])->assertOk();
        $this->assertSame(0, $emp->activeModelAssignments()->count());

        $history = $this->getJson("{$url}/history")->assertOk();
        $this->assertCount(3, $history->json('data.model_history'));
    }

    public function test_model_assignments_are_scoped_to_the_employees_client(): void
    {
        $foreignModel = $this->createModel($this->clientB);
        $ownModel = $this->createModel($this->clientA);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);
        $url = "/api/client-assets/workwear-employees/{$emp->id}";

        $this->putJson($url, ['model_assignments' => [['model_id' => $foreignModel, 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('model_assignments');

        $this->putJson($url, ['model_assignments' => [['model_id' => $ownModel, 'quantity' => 1], ['model_id' => $ownModel, 'quantity' => 2]]])
            ->assertStatus(422)->assertJsonValidationErrors('model_assignments');

        $this->putJson($url, ['model_assignments' => [['model_id' => $ownModel, 'quantity' => 0]]])
            ->assertStatus(422)->assertJsonValidationErrors('model_assignments.0.quantity');

        $this->assertDatabaseCount('workwear_employee_models', 0);

        // Once an entitlement exists the employee can no longer move to another client.
        $this->putJson($url, ['model_assignments' => [['model_id' => $ownModel, 'quantity' => 1]]])->assertOk();
        $this->putJson($url, ['client_id' => $this->clientB->id])->assertStatus(422)->assertJsonValidationErrors('client_id');
    }

    // ─── Items ──────────────────────────────────────────────────

    public function test_item_variant_fields_sku_default_and_filters(): void
    {
        $supplier = Supplier::create(['legal_name' => 'Textile Co', 'supplier_category' => 'distributor', 'status' => 'active']);
        $modelId = $this->createModel($this->clientA);

        $auto = $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id, 'model_id' => $modelId,
            'size' => 'L', 'color' => 'Navy', 'supplier_id' => $supplier->id, 'unit_cost' => 42.5,
        ])->assertCreated();

        $this->assertMatchesRegularExpression('/^WM-.+-NAVY-L$/', $auto->json('data.sku'));
        $auto->assertJsonPath('data.color', 'Navy')->assertJsonPath('data.supplier.legal_name', 'Textile Co');

        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id, 'model_id' => $modelId, 'size' => 'M', 'sku' => 'CUSTOM-1',
        ])->assertCreated()->assertJsonPath('data.sku', 'CUSTOM-1');

        $this->getJson('/api/client-assets/workwear-items?filter[supplier_id]='.$supplier->id)->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/client-assets/workwear-items?filter[color]=Navy')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/client-assets/workwear-items?q=CUSTOM-1')->assertOk()->assertJsonPath('total', 1);

        $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id, 'model_id' => $modelId, 'supplier_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
        ])->assertStatus(422)->assertJsonValidationErrors('supplier_id');
    }

    public function test_batch_intake_applies_header_supplier_and_row_variants(): void
    {
        $supplier = Supplier::create(['legal_name' => 'Textile Co', 'supplier_category' => 'distributor', 'status' => 'active']);
        $modelId = $this->createModel($this->clientA);

        $res = $this->postJson('/api/client-assets/workwear-items/batch', [
            'client_id' => $this->clientA->id, 'model_id' => $modelId, 'supplier_id' => $supplier->id,
            'items' => [['size' => 'S', 'color' => 'Red'], ['size' => 'M', 'color' => 'Red', 'sku' => 'RED-M']],
        ])->assertCreated();

        $this->assertSame($supplier->id, $res->json('data.0.supplier_id'));
        $this->assertSame('RED-M', $res->json('data.1.sku'));
    }

    public function test_stock_summary_groups_variants_and_never_touches_inventory(): void
    {
        $modelId = $this->createModel($this->clientA);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);

        $a = $this->item($modelId, $this->clientA, ['size' => 'L', 'color' => 'Navy', 'sku' => 'V-NAVY-L']);
        $this->item($modelId, $this->clientA, ['size' => 'L', 'color' => 'Navy', 'sku' => 'V-NAVY-L']);
        $this->item($modelId, $this->clientA, ['size' => 'L', 'color' => 'Navy', 'sku' => 'V-NAVY-L', 'status' => 'retired']);
        $this->item($modelId, $this->clientA, ['size' => 'M', 'color' => 'Navy', 'sku' => 'V-NAVY-M', 'status' => 'repair_needed']);
        $this->postJson('/api/client-assets/workwear-items/assign', ['employee_id' => $emp->id, 'item_ids' => [$a->id]])->assertOk();

        $rows = collect($this->getJson("/api/client-assets/workwear-stock?filter[model_id]={$modelId}")->assertOk()->json('data'));

        $l = $rows->firstWhere('sku', 'V-NAVY-L');
        $this->assertSame(['total' => 2, 'available' => 1, 'assigned' => 1, 'repair_needed' => 0], collect($l)->only(['total', 'available', 'assigned', 'repair_needed'])->all());

        $m = $rows->firstWhere('sku', 'V-NAVY-M');
        $this->assertSame(1, $m['repair_needed']);
        $this->assertSame(0, $m['available']);

        $this->assertSame(0, $this->getJson('/api/client-assets/workwear-stock?filter[client_id]='.$this->clientB->id)->json('data') === [] ? 0 : 1);
    }

    public function test_item_photos_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $item = $this->item($this->createModel($this->clientA), $this->clientA);

        $res = $this->postJson("/api/client-assets/workwear-items/{$item->id}/media", [
            'file' => UploadedFile::fake()->image('front.jpg', 400, 400),
        ])->assertCreated()->assertJsonCount(1, 'data.photos');

        $this->deleteJson("/api/client-assets/workwear-items/{$item->id}/media/".$res->json('data.photos.0.id'))->assertOk();
        $this->getJson("/api/client-assets/workwear-items/{$item->id}")->assertJsonCount(0, 'data.photos');

        $this->postJson("/api/client-assets/workwear-items/{$item->id}/media", [
            'file' => UploadedFile::fake()->create('malware.exe', 10),
        ])->assertStatus(422);
    }

    public function test_kpis_active_assignments_are_scoped_to_the_client(): void
    {
        $modelA = $this->createModel($this->clientA);
        $empA = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);
        $this->postJson('/api/client-assets/workwear-items/assign', ['employee_id' => $empA->id, 'item_ids' => [$this->item($modelA, $this->clientA)->id]])->assertOk();

        $this->getJson('/api/client-assets/workwear-kpis?client_id='.$this->clientA->id)->assertJsonPath('data.active_assignments', 1);
        $this->getJson('/api/client-assets/workwear-kpis?client_id='.$this->clientB->id)->assertJsonPath('data.active_assignments', 0);
    }

    // ─── Authorization ──────────────────────────────────────────

    public function test_users_without_permissions_cannot_use_the_new_endpoints(): void
    {
        $modelId = $this->createModel($this->clientA);
        $emp = WorkwearEmployee::create(['client_id' => $this->clientA->id, 'first_name' => 'E', 'last_name' => 'F']);
        $item = $this->item($modelId, $this->clientA);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/client-assets/workwear-stock')->assertForbidden();
        $this->putJson("/api/client-assets/workwear-models/{$modelId}", ['placements' => [$this->placement()]])->assertForbidden();
        $this->putJson("/api/client-assets/workwear-employees/{$emp->id}", ['model_assignments' => [['model_id' => $modelId, 'quantity' => 1]]])->assertForbidden();
        $this->postJson("/api/client-assets/workwear-items/{$item->id}/media", ['file' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
        $this->assertDatabaseCount('workwear_model_placements', 0);
        $this->assertDatabaseCount('workwear_employee_models', 0);
    }

    // ─── Workwear Fabrics, Physical Catalog Integration & Expanded Specs ───

    public function test_workwear_fabrics_crud_and_delete_guard(): void
    {
        $created = $this->postJson('/api/client-assets/workwear-fabrics', [
            'code' => 'fab-nomex-260',
            'name' => 'Nomex Aramid 260 g/m²',
            'composition' => '93% Meta-Aramid / 5% Para-Aramid / 2% Carbon',
            'weight_gsm' => 260,
            'weave_type' => 'Ripstop',
            'properties' => ['Inherent FR', 'Antistatic EN 1149-5'],
        ])->assertCreated()
            ->assertJsonPath('data.code', 'FAB-NOMEX-260')
            ->assertJsonPath('data.weight_gsm', 260);

        $fabricId = $created->json('data.id');

        // Link to a workwear model and verify fabric name is synced
        $modelRes = $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $this->clientA->id,
            'name' => 'Offshore Aramid Suit',
            'fabric_id' => $fabricId,
        ])->assertCreated()
            ->assertJsonPath('data.fabric_id', $fabricId)
            ->assertJsonPath('data.fabric', 'Nomex Aramid 260 g/m²');

        // Updating fabric name updates the denormalized label on linked models
        $this->putJson("/api/client-assets/workwear-fabrics/{$fabricId}", [
            'name' => 'Nomex IIIA Aramid 260 g/m²',
        ])->assertOk();

        $this->getJson('/api/client-assets/workwear-models/'.$modelRes->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.fabric', 'Nomex IIIA Aramid 260 g/m²');

        // Cannot delete fabric while linked to a model
        $this->deleteJson("/api/client-assets/workwear-fabrics/{$fabricId}")->assertStatus(422);
    }

    public function test_workwear_model_links_physical_catalog_item_and_syncs_preferred_supplier(): void
    {
        $supplier = Supplier::create([
            'legal_name' => 'Delta Plus PPE Manufacturing',
            'commercial_name' => 'Delta Plus',
            'status' => 'active',
        ]);

        $category = CatalogCategory::create([
            'name' => ['en' => 'Workwear & PPE'],
            'item_type_scope' => ['product', 'consumable'],
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $uom = UnitOfMeasure::create([
            'code' => 'PCS',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);

        $physicalItem = CatalogItem::create([
            'code' => 'CAT-WW-001',
            'item_type' => 'product',
            'name' => ['en' => 'Hi-Vis Parka 4-in-1'],
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'preferred_supplier_id' => $supplier->id,
            'status' => 'active',
        ]);
        CatalogProduct::create([
            'catalog_item_id' => $physicalItem->id,
            'product_type' => 'physical',
        ]);

        $consumableItem = CatalogItem::create([
            'code' => 'CAT-CONS-001',
            'item_type' => 'consumable',
            'name' => ['en' => 'Cleaning Spray'],
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'status' => 'active',
        ]);

        // Rejects non-product catalog item
        $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $this->clientA->id,
            'name' => 'Invalid Model',
            'catalog_item_id' => $consumableItem->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['catalog_item_id']);

        // Accepts physical product and auto-populates supplier_id from preferred_supplier_id
        $res = $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $this->clientA->id,
            'name' => 'Winter Parka Model',
            'catalog_item_id' => $physicalItem->id,
            'silhouette_category' => 'high_visibility',
            'silhouette_subcategory' => 'hivis_class_3',
            'silhouette_item' => 'hivis_parka_4in1_c3',
            'silhouette' => 'parka',
            'standards' => ['EN ISO 20471', 'EN 343'],
            'certifications' => ['CE Category II (Intermediate Risk — Notified Body Type-Examined)'],
            'technical_specs' => [
                'fastening' => '2-Way Concealed YKK Zip + Storm Flap',
                'visibility' => '50 mm Sewn Microprismatic Reflective Tape',
                'adjustability' => 'Velcro Tab Adjustable Cuffs & Ankle Hems',
                'collar' => 'Detachable Helmet-Compatible Storm Hood',
            ],
            'custom_made_requirements' => [
                'is_custom_made' => true,
                'fit_variation' => 'unisex_regular',
                'moq' => 50,
                'lead_time_days' => 21,
                'construction_requirements' => ['Client Corporate Pantone Dyed Fabric'],
            ],
            'placements' => [
                $this->placement(['type' => 'fluo', 'label' => 'Fluo Yoke', 'zone' => 'chest_center']),
                $this->placement(['type' => 'flag', 'label' => 'National Flag', 'zone' => 'sleeve_left']),
            ],
        ])->assertCreated()
            ->assertJsonPath('data.catalog_item_id', $physicalItem->id)
            ->assertJsonPath('data.supplier_id', $supplier->id)
            ->assertJsonPath('data.silhouette_category', 'high_visibility')
            ->assertJsonPath('data.silhouette_subcategory', 'hivis_class_3')
            ->assertJsonPath('data.silhouette_item', 'hivis_parka_4in1_c3')
            ->assertJsonPath('data.silhouette', 'parka')
            ->assertJsonPath('data.standards', ['EN ISO 20471', 'EN 343'])
            ->assertJsonPath('data.custom_made_requirements.is_custom_made', true)
            ->assertJsonPath('data.custom_made_requirements.moq', 50)
            ->assertJsonCount(2, 'data.placements');

        $this->assertSame('fluo', $res->json('data.placements.0.type'));
        $this->assertSame('flag', $res->json('data.placements.1.type'));
    }

    public function test_studio_template_validates_garment_view_zone_dimensions_and_coordinates(): void
    {
        $base = ['client_id' => $this->clientA->id, 'name' => 'Template Validation Model'];

        // 1. Zone not allowed for silhouette (e.g. chest_left on trousers, knee_left on polo, sleeve_left on vest)
        $this->postJson('/api/client-assets/workwear-models', $base + [
            'silhouette' => 'trousers',
            'placements' => [$this->placement(['zone' => 'chest_left', 'view' => 'front'])],
        ])->assertStatus(422)->assertJsonValidationErrors('placements.0.zone');

        $this->postJson('/api/client-assets/workwear-models', $base + [
            'silhouette' => 'polo',
            'placements' => [$this->placement(['zone' => 'knee_left', 'view' => 'front'])],
        ])->assertStatus(422)->assertJsonValidationErrors('placements.0.zone');

        $this->postJson('/api/client-assets/workwear-models', $base + [
            'silhouette' => 'vest',
            'placements' => [$this->placement(['zone' => 'sleeve_left', 'view' => 'front'])],
        ])->assertStatus(422)->assertJsonValidationErrors('placements.0.zone');

        // 2. View not supported for zone on template (chest_left is front-only; back_upper is back-only)
        $this->postJson('/api/client-assets/workwear-models', $base + [
            'silhouette' => 'coverall',
            'placements' => [$this->placement(['zone' => 'chest_left', 'view' => 'back'])],
        ])->assertStatus(422)->assertJsonValidationErrors('placements.0.view');

        $this->postJson('/api/client-assets/workwear-models', $base + [
            'silhouette' => 'jacket',
            'placements' => [$this->placement(['zone' => 'back_upper', 'view' => 'front'])],
        ])->assertStatus(422)->assertJsonValidationErrors('placements.0.view');

        // 3. Dimensions exceeding zone limits / safe box
        $this->postJson('/api/client-assets/workwear-models', $base + [
            'silhouette' => 'polo',
            'placements' => [$this->placement(['zone' => 'chest_left', 'view' => 'front', 'width_mm' => 320, 'height_mm' => 240])],
        ])->assertStatus(422)->assertJsonValidationErrors(['placements.0.width_mm', 'placements.0.height_mm']);

        // 4. Coordinates / offsets pushing placement outside the zone's safe boundary
        $this->postJson('/api/client-assets/workwear-models', $base + [
            'silhouette' => 'coverall',
            'placements' => [$this->placement(['zone' => 'chest_left', 'view' => 'front', 'width_mm' => 80, 'height_mm' => 35, 'offset_x_mm' => 140, 'offset_y_mm' => -120])],
        ])->assertStatus(422)->assertJsonValidationErrors(['placements.0.offset_x_mm', 'placements.0.offset_y_mm']);

        // 5. Valid placements across Trousers, Vest, Polo, Jacket and Coverall succeed and expose unverified scale + color_regions
        $trousersRes = $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $this->clientA->id,
            'name' => 'Cargo Trousers Model',
            'silhouette' => 'trousers',
            'colors' => ['navy', 'hi-vis-yellow', 'knee_reinforcements:black'],
            'placements' => [
                $this->placement(['zone' => 'thigh_left', 'view' => 'front', 'width_mm' => 80, 'height_mm' => 35, 'offset_x_mm' => 0, 'offset_y_mm' => 0]),
                $this->placement(['zone' => 'back_lower', 'view' => 'back', 'width_mm' => 100, 'height_mm' => 30, 'offset_x_mm' => 0, 'offset_y_mm' => 0]),
            ],
        ])->assertCreated();

        $trousersRes->assertJsonPath('data.studio_scale.scale_verified', false)
            ->assertJsonPath('data.color_regions.main_fabric', 'navy')
            ->assertJsonPath('data.color_regions.cargo_accents', 'hi-vis-yellow')
            ->assertJsonPath('data.color_regions.knee_reinforcements', 'black');
    }

    public function test_backward_compatible_normalization_preserves_legacy_placements_and_uniform_scale(): void
    {
        // Uniform scale: 10mm offset shift in SVG units equals 10mm dimension width in SVG units
        $geomA = WorkwearStudioTemplateCatalog::computePlacementGeometry('coverall', [
            'zone' => 'chest_left',
            'view' => 'front',
            'width_mm' => 80,
            'height_mm' => 40,
            'offset_x_mm' => 0,
            'offset_y_mm' => 0,
        ]);
        $geomB = WorkwearStudioTemplateCatalog::computePlacementGeometry('coverall', [
            'zone' => 'chest_left',
            'view' => 'front',
            'width_mm' => 90,
            'height_mm' => 40,
            'offset_x_mm' => 10,
            'offset_y_mm' => 0,
        ]);

        $this->assertFalse($geomA['scale_verified']);
        $this->assertSame(round($geomB['w'] - $geomA['w'], 4), round($geomB['cx'] - $geomA['cx'], 4));

        // Legacy placement with incompatible zone/view/dimensions is safely normalized for target silhouette
        $normalized = WorkwearStudioTemplateCatalog::normalizePlacementForTemplate('trousers', [
            'zone' => 'chest_left',
            'view' => 'back',
            'type' => 'logo',
            'label' => 'Legacy Logo',
            'width_mm' => 500,
            'height_mm' => 400,
            'offset_x_mm' => 300,
            'offset_y_mm' => -300,
        ]);

        $this->assertSame('pocket_left', $normalized['zone']);
        $this->assertSame('back', $normalized['view']);
        $check = WorkwearStudioTemplateCatalog::computePlacementGeometry('trousers', $normalized);
        $this->assertTrue($check['within_safe_bounds']);
    }
}
