<?php

declare(strict_types=1);

namespace Tests\Feature\ClientAssets;

use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Enums\WorkwearItemCondition;
use App\Modules\ClientAssets\Enums\WorkwearItemStatus;
use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Models\WorkwearModelOption;
use App\Modules\ClientAssets\Models\WorkwearModelOptionValue;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkwearFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $clientA;

    private Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->clientA = Client::create([
            'legal_name' => 'Acme Corporation SARL',
            'commercial_name' => 'Acme',
            'status' => 'active',
        ]);

        $this->clientB = Client::create([
            'legal_name' => 'Beta Logistics SA',
            'commercial_name' => 'Beta',
            'status' => 'active',
        ]);
    }

    // ─── 1. Strict Business Boundary: No Inventory Impact ──────

    public function test_workwear_operations_do_not_create_or_modify_inventory_stock(): void
    {
        $initialStockItemsCount = StockItem::count();
        $initialStockMovementsCount = StockMovement::count();

        // 1. Create Model
        $model = WorkwearModel::create([
            'client_id' => $this->clientA->id,
            'name' => 'Industrial Hi-Vis Vest',
            'status' => 'active',
        ]);

        // 2. Batch Create Items
        $response = $this->postJson('/api/client-assets/workwear-items/batch', [
            'client_id' => $this->clientA->id,
            'model_id' => $model->id,
            'items' => [
                ['garment_type' => 'vest', 'size' => 'L', 'unit_cost' => 25.50],
                ['garment_type' => 'vest', 'size' => 'XL', 'unit_cost' => 25.50],
            ],
        ]);
        $response->assertStatus(201);

        // 3. Create Employee
        $emp = WorkwearEmployee::create([
            'client_id' => $this->clientA->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        $itemIds = WorkwearItem::where('client_id', $this->clientA->id)->pluck('id')->toArray();

        // 4. Assign Items
        $assignResponse = $this->postJson('/api/client-assets/workwear-items/assign', [
            'employee_id' => $emp->id,
            'item_ids' => $itemIds,
        ]);
        $assignResponse->assertStatus(200);

        // 5. Return Item
        $assignmentId = $assignResponse->json('data.0.id');
        $returnResponse = $this->postJson("/api/client-assets/assignments/{$assignmentId}/return", [
            'return_action' => 'restock',
            'condition_at_return' => 'good',
        ]);
        $returnResponse->assertStatus(200);

        // Assert zero changes to stock tables
        $this->assertSame($initialStockItemsCount, StockItem::count(), 'Workwear must not create inventory StockItems');
        $this->assertSame($initialStockMovementsCount, StockMovement::count(), 'Workwear must not create StockMovements');
    }

    // ─── 2. Model & Options Lifecycle ───────────────────────────

    public function test_workwear_model_with_options_and_option_guard(): void
    {
        // Create model with options
        $response = $this->postJson('/api/client-assets/workwear-models', [
            'client_id' => $this->clientA->id,
            'name' => 'Heavy Winter Parka',
            'fabric' => 'Gore-Tex 300g',
            'status' => 'active',
            'options' => [
                [
                    'name' => 'Size',
                    'ui_control' => 'select',
                    'sort_order' => 1,
                    'values' => [
                        ['value' => 'M'],
                        ['value' => 'L'],
                    ],
                ],
            ],
        ]);
        $response->assertStatus(201);
        $modelId = $response->json('data.id');

        $this->assertDatabaseHas('workwear_model_options', ['workwear_model_id' => $modelId, 'name' => 'Size']);
        $this->assertDatabaseHas('workwear_model_option_values', ['value' => 'M']);
        $this->assertDatabaseHas('workwear_model_option_values', ['value' => 'L']);

        // Add a second option via dedicated endpoint
        $optResponse = $this->postJson("/api/client-assets/workwear-models/{$modelId}/options", [
            'name' => 'Color',
            'ui_control' => 'select',
            'values' => ['Navy Blue', 'Safety Orange'],
        ]);
        $optResponse->assertStatus(201);
        $colorOptId = $optResponse->json('data.id');

        // Create item linked to one of the option values
        $sizeValue = WorkwearModelOptionValue::where('value', 'M')->first();
        $item = WorkwearItem::create([
            'client_id' => $this->clientA->id,
            'model_id' => $modelId,
            'garment_type' => 'parka',
            'status' => WorkwearItemStatus::IN_STOCK->value,
        ]);

        $this->putJson("/api/client-assets/workwear-items/{$item->id}", [
            'option_value_ids' => [$sizeValue->id],
        ]);

        // Attempt to delete option 'Size' while in use -> must fail 422
        $sizeOption = WorkwearModelOption::where('workwear_model_id', $modelId)->where('name', 'Size')->first();
        $delOptResponse = $this->deleteJson("/api/client-assets/workwear-models/{$modelId}/options/{$sizeOption->id}");
        $delOptResponse->assertStatus(422);

        // Deleting unused 'Color' option -> must succeed
        $delColorResponse = $this->deleteJson("/api/client-assets/workwear-models/{$modelId}/options/{$colorOptId}");
        $delColorResponse->assertStatus(200);

        // Attempt to delete model while items exist -> must fail 422
        $delModelResponse = $this->deleteJson("/api/client-assets/workwear-models/{$modelId}");
        $delModelResponse->assertStatus(422);
    }

    // ─── 3. Employees & Batch Creation ──────────────────────────

    public function test_workwear_employee_batch_and_delete_protection(): void
    {
        $batchResponse = $this->postJson('/api/client-assets/workwear-employees/batch', [
            'client_id' => $this->clientA->id,
            'employees' => [
                [
                    'first_name' => 'Alice',
                    'last_name' => 'Smith',
                    'email' => 'alice@acme.com',
                    'department' => 'Operations',
                    'position' => 'Team Lead',
                    'sizes' => ['top' => 'M', 'bottom' => '32', 'shoes' => '39'],
                ],
                [
                    'first_name' => 'Bob',
                    'last_name' => 'Jones',
                    'email' => 'bob@acme.com',
                    'department' => 'Maintenance',
                    'position' => 'Technician',
                    'sizes' => ['top' => 'XL', 'bottom' => '36', 'shoes' => '44'],
                ],
            ],
        ]);
        $batchResponse->assertStatus(201);
        $this->assertCount(2, $batchResponse->json('data'));

        $aliceId = $batchResponse->json('data.0.id');
        $bobId = $batchResponse->json('data.1.id');

        // Create item and assign to Alice
        $model = WorkwearModel::create(['client_id' => $this->clientA->id, 'name' => 'Safety Boots']);
        $item = WorkwearItem::create([
            'client_id' => $this->clientA->id,
            'model_id' => $model->id,
            'status' => WorkwearItemStatus::IN_STOCK->value,
        ]);

        $this->postJson('/api/client-assets/workwear-items/assign', [
            'employee_id' => $aliceId,
            'item_ids' => [$item->id],
        ]);

        // Attempt to delete Alice while she has an active assignment -> must fail 422
        $delAliceResponse = $this->deleteJson("/api/client-assets/workwear-employees/{$aliceId}");
        $delAliceResponse->assertStatus(422);

        // Bob has no assignments -> delete must succeed
        $delBobResponse = $this->deleteJson("/api/client-assets/workwear-employees/{$bobId}");
        $delBobResponse->assertStatus(200);
    }

    // ─── 4. Cross-Entity Validation & Assignments ───────────────

    public function test_cross_client_protection_and_multi_item_assignment(): void
    {
        // Model for Client A
        $modelA = WorkwearModel::create(['client_id' => $this->clientA->id, 'name' => 'Jacket A']);
        // Model for Client B
        $modelB = WorkwearModel::create(['client_id' => $this->clientB->id, 'name' => 'Jacket B']);

        // 1. Rejection when trying to create item for Client A using Model B
        $crossItemResponse = $this->postJson('/api/client-assets/workwear-items', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelB->id,
            'garment_type' => 'jacket',
        ]);
        $crossItemResponse->assertStatus(422);
        $crossItemResponse->assertJsonValidationErrors('model_id');

        // Batch creation with cross-client model also rejected
        $crossBatchResponse = $this->postJson('/api/client-assets/workwear-items/batch', [
            'client_id' => $this->clientA->id,
            'model_id' => $modelB->id,
            'items' => [['size' => 'M']],
        ]);
        $crossBatchResponse->assertStatus(422);

        // 2. Multi-item assignment
        $item1 = WorkwearItem::create([
            'client_id' => $this->clientA->id,
            'model_id' => $modelA->id,
            'status' => WorkwearItemStatus::IN_STOCK->value,
        ]);
        $item2 = WorkwearItem::create([
            'client_id' => $this->clientA->id,
            'model_id' => $modelA->id,
            'status' => WorkwearItemStatus::IN_STOCK->value,
        ]);
        $empA = WorkwearEmployee::create([
            'client_id' => $this->clientA->id,
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
        ]);
        $empB = WorkwearEmployee::create([
            'client_id' => $this->clientB->id,
            'first_name' => 'David',
            'last_name' => 'Miller',
        ]);

        // Attempt cross-client assignment (Item of Client A to Employee of Client B) -> rejected
        $crossAssignResponse = $this->postJson('/api/client-assets/workwear-items/assign', [
            'employee_id' => $empB->id,
            'item_ids' => [$item1->id],
        ]);
        $this->assertNotEmpty($crossAssignResponse->json('errors'));

        // Valid assignment of both items to empA
        $validAssignResponse = $this->postJson('/api/client-assets/workwear-items/assign', [
            'employee_id' => $empA->id,
            'item_ids' => [$item1->id, $item2->id],
            'notes' => 'Seasonal distribution',
        ]);
        $validAssignResponse->assertStatus(200);
        $this->assertCount(2, $validAssignResponse->json('data'));

        // Items are now marked as assigned
        $this->assertSame(WorkwearItemStatus::ASSIGNED, $item1->fresh()->status);
        $this->assertSame(WorkwearItemStatus::ASSIGNED, $item2->fresh()->status);

        // Re-assigning an already assigned item -> rejected
        $duplicateAssign = $this->postJson('/api/client-assets/workwear-items/assign', [
            'employee_id' => $empA->id,
            'item_ids' => [$item1->id],
        ]);
        $this->assertNotEmpty($duplicateAssign->json('errors'));
    }

    // ─── 5. Return Lifecycle & State Transitions ────────────────

    public function test_return_inspection_state_transitions(): void
    {
        $model = WorkwearModel::create(['client_id' => $this->clientA->id, 'name' => 'Standard Coverall']);
        $item = WorkwearItem::create([
            'client_id' => $this->clientA->id,
            'model_id' => $model->id,
            'status' => WorkwearItemStatus::IN_STOCK->value,
        ]);
        $emp = WorkwearEmployee::create([
            'client_id' => $this->clientA->id,
            'first_name' => 'Eve',
            'last_name' => 'White',
        ]);

        // Assign
        $assignResponse = $this->postJson('/api/client-assets/workwear-items/assign', [
            'employee_id' => $emp->id,
            'item_ids' => [$item->id],
        ]);
        $assignmentId = $assignResponse->json('data.0.id');

        // Return with 'repair' action
        $returnRepair = $this->postJson("/api/client-assets/assignments/{$assignmentId}/return", [
            'return_action' => 'repair',
            'condition_at_return' => 'damaged',
            'notes' => 'Torn sleeve',
        ]);
        $returnRepair->assertStatus(200);

        $item->refresh();
        $this->assertSame(WorkwearItemStatus::REPAIR_NEEDED, $item->status);
        $this->assertSame(WorkwearItemCondition::DAMAGED, $item->item_condition);

        // Assignment cannot be returned again
        $secondReturn = $this->postJson("/api/client-assets/assignments/{$assignmentId}/return", [
            'return_action' => 'restock',
        ]);
        $secondReturn->assertStatus(422);
    }

    // ─── 6. KPIs and Public QR Scanning ─────────────────────────

    public function test_kpis_and_public_qr_scan(): void
    {
        $model = WorkwearModel::create(['client_id' => $this->clientA->id, 'name' => 'Safety Helmet']);
        $item = WorkwearItem::create([
            'client_id' => $this->clientA->id,
            'model_id' => $model->id,
            'garment_type' => 'helmet',
            'size' => 'Universal',
            'status' => WorkwearItemStatus::IN_STOCK->value,
        ]);

        // KPIs endpoint
        $kpiResponse = $this->getJson("/api/client-assets/workwear-kpis?client_id={$this->clientA->id}");
        $kpiResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $kpiResponse->json('data.total_items'));
        $this->assertGreaterThanOrEqual(1, $kpiResponse->json('data.in_stock'));

        // Public scan (No authentication)
        Sanctum::actingAs(User::factory()->make()); // simulate guest by removing token
        $publicScan = $this->getJson("/api/ca/scan/ww/{$item->uuid}");
        $publicScan->assertStatus(200);
        $publicScan->assertJsonStructure([
            'data' => [
                'item_code',
                'garment_type',
                'size',
                'status',
                'is_stolen',
                'model' => ['name', 'model_ref'],
            ],
        ]);
    }

    public function test_workwear_qr_download_response_content_type_and_authorization(): void
    {
        $model = WorkwearModel::create(['client_id' => $this->clientA->id, 'name' => 'High-Vis Parka']);
        $item = WorkwearItem::create([
            'client_id' => $this->clientA->id,
            'model_id' => $model->id,
            'garment_type' => 'jacket',
            'size' => 'L',
            'status' => WorkwearItemStatus::IN_STOCK->value,
        ]);

        // 1. Unauthenticated request rejected
        // Clear Sanctum token/user authentication
        app('auth')->forgetGuards();
        $unauth = $this->getJson("/api/client-assets/workwear-items/{$item->id}/qr/download");
        $unauth->assertStatus(401);

        // 2. Authenticated user without required role/permission rejected
        $unauthorizedUser = User::factory()->create();
        Sanctum::actingAs($unauthorizedUser);
        $forbidden = $this->getJson("/api/client-assets/workwear-items/{$item->id}/qr/download");
        $forbidden->assertStatus(403);

        // 3. Authorized Super Admin successfully downloads PNG
        Sanctum::actingAs($this->user);
        $response = $this->get("/api/client-assets/workwear-items/{$item->id}/qr/download");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString("attachment; filename=\"qr-{$item->item_code}.png\"", (string) $response->headers->get('Content-Disposition'));

        // Verify generated content is valid PNG binary
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($content, 0, 8), 'Response content must begin with valid PNG magic bytes');

        $img = @imagecreatefromstring($content);
        $this->assertNotFalse($img, 'Generated content must be decodable as a valid PNG image');
        imagedestroy($img);

        // 4. Test route alias /qr-code
        $aliasResponse = $this->get("/api/client-assets/workwear-items/{$item->id}/qr-code");
        $aliasResponse->assertStatus(200);
        $aliasResponse->assertHeader('Content-Type', 'image/png');
    }
}
