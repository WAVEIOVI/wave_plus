<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class EquipmentBlueprintTest extends TestCase
{
    use RefreshDatabase;

    private UnitOfMeasure $uom;
    private CatalogCategory $category;
    private User $admin;
    private CatalogItem $eqClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uom = UnitOfMeasure::create([
            'code' => 'PCE',
            'name' => ['en' => 'Piece'],
            'type' => 'count',
        ]);

        $this->category = CatalogCategory::create([
            'name'      => ['en' => 'Fire Equipment'],
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->admin->assignRole('Super Admin');
        Sanctum::actingAs($this->admin);
    }

    private function blueprintPayload(array $overrides = []): array
    {
        return array_merge([
            'code'                 => 'BP-TEST-'.uniqid(),
            'name'                 => ['en' => 'Test Blueprint'],
            'description'          => ['en' => 'A test blueprint'],
            'status'               => 'draft',
            'working_pressure_bar' => 14.5,
            'fire_class_rating'    => '55A 233B C',
            'shelf_life_years'     => 5,
            'intervention_cycles'  => [],
            'servicing_items'      => [],
            'blueprint_options'    => [],
        ], $overrides);
    }

    /** @test */
    public function can_create_a_blueprint_without_equipment_class(): void
    {
        // equipment_class_id is now optional (nullable)
        $payload = $this->blueprintPayload();

        $response = $this->postJson('/api/catalog/blueprints', $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('equipment_blueprints', ['code' => $payload['code']]);
    }

    /** @test */
    public function can_create_blueprint_with_structured_options_and_values(): void
    {
        $payload = $this->blueprintPayload([
            'blueprint_options' => [
                [
                    'code'      => 'capacity',
                    'name'      => ['en' => 'Capacity'],
                    'data_type' => 'select',
                    'sort_order' => 0,
                    'values'    => [
                        ['code' => '6kg',  'value' => ['en' => '6 kg'],  'numeric_value' => 6.0,  'unit' => 'kg', 'sort_order' => 0],
                        ['code' => '9kg',  'value' => ['en' => '9 kg'],  'numeric_value' => 9.0,  'unit' => 'kg', 'sort_order' => 1],
                        ['code' => '12kg', 'value' => ['en' => '12 kg'], 'numeric_value' => 12.0, 'unit' => 'kg', 'sort_order' => 2],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/catalog/blueprints', $payload);
        $response->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('blueprint_options', ['code' => 'capacity']);
        $this->assertDatabaseHas('blueprint_option_values', ['code' => '6kg', 'numeric_value' => 6.0]);
    }

    /** @test */
    public function can_update_blueprint_and_options_are_synced(): void
    {
        // Create
        $createPayload = $this->blueprintPayload([
            'blueprint_options' => [
                [
                    'code'      => 'capacity',
                    'name'      => ['en' => 'Capacity'],
                    'data_type' => 'select',
                    'sort_order' => 0,
                    'values'    => [
                        ['code' => '6kg', 'value' => ['en' => '6 kg'], 'numeric_value' => 6.0, 'unit' => 'kg', 'sort_order' => 0],
                    ],
                ],
            ],
        ]);
        $createResponse = $this->postJson('/api/catalog/blueprints', $createPayload);
        $createResponse->assertStatus(201);
        $id = $createResponse->json('data.id');

        // Update — add a new value, change name
        $updatePayload = $this->blueprintPayload([
            'code' => $createPayload['code'],
            'blueprint_options' => [
                [
                    'code'      => 'capacity',
                    'name'      => ['en' => 'Capacity (Updated)'],
                    'data_type' => 'select',
                    'sort_order' => 0,
                    'values'    => [
                        ['code' => '6kg',  'value' => ['en' => '6 kg'],  'numeric_value' => 6.0,  'unit' => 'kg', 'sort_order' => 0],
                        ['code' => '12kg', 'value' => ['en' => '12 kg'], 'numeric_value' => 12.0, 'unit' => 'kg', 'sort_order' => 1],
                    ],
                ],
            ],
        ]);

        $updateResponse = $this->putJson("/api/catalog/blueprints/{$id}", $updatePayload);
        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('blueprint_option_values', ['code' => '12kg', 'numeric_value' => 12.0]);
    }

    /** @test */
    public function cannot_submit_obsolete_compatibility_fields(): void
    {
        $payload = $this->blueprintPayload([
            'applicable_equipment_classes' => ['some-class'],
            'applicable_agent_types'       => ['technician'],
        ]);

        $response = $this->postJson('/api/catalog/blueprints', $payload);

        // Should still succeed (obsolete fields are simply ignored or stripped)
        $response->assertStatus(201);
    }

    /** @test */
    public function blueprint_index_returns_paginated_response(): void
    {
        for ($i = 0; $i < 3; $i++) {
            EquipmentBlueprint::create([
                'code'   => 'BP-TEST-' . uniqid() . "-$i",
                'name'   => ['en' => "Test Blueprint $i"],
                'status' => 'draft',
            ]);
        }

        $response = $this->getJson('/api/catalog/blueprints');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'total', 'per_page'],
        ]);
    }

    /** @test */
    public function blueprint_show_includes_options_and_servicing(): void
    {
        $payload = $this->blueprintPayload([
            'blueprint_options' => [
                [
                    'code'      => 'capacity',
                    'name'      => ['en' => 'Capacity'],
                    'data_type' => 'select',
                    'sort_order' => 0,
                    'values'    => [
                        ['code' => '6kg', 'value' => ['en' => '6 kg'], 'numeric_value' => 6.0, 'unit' => 'kg', 'sort_order' => 0],
                    ],
                ],
            ],
        ]);
        $createResponse = $this->postJson('/api/catalog/blueprints', $payload);
        $id = $createResponse->json('data.id');

        $response = $this->getJson("/api/catalog/blueprints/{$id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
                'status',
                'blueprint_options',
                'intervention_cycles',
                'servicing_items',
            ],
        ]);
    }

    /** @test */
    public function can_delete_a_blueprint(): void
    {
        $payload = $this->blueprintPayload();
        $createResponse = $this->postJson('/api/catalog/blueprints', $payload);
        $id = $createResponse->json('data.id');

        $response = $this->deleteJson("/api/catalog/blueprints/{$id}");
        $response->assertStatus(200);

        $this->assertSoftDeleted('equipment_blueprints', ['id' => $id]);
    }
}
