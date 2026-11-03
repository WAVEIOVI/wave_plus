<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_viewer_cannot_complete_training(): void
    {
        $role = Role::findOrCreate('Training Viewer', 'web');
        Permission::findOrCreate('hr.training.view', 'web');
        $role->givePermissionTo('hr.training.view');

        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        Sanctum::actingAs($viewer);

        $this->postJson('/api/hr/training/participants/'.Str::ulid().'/complete', [
            'status' => 'completed',
        ])->assertForbidden();
    }
}
