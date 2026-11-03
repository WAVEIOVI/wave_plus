<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(string $role = 'Admin'): User
    {
        $user = User::factory()->create();
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);

        return $user;
    }

    public function test_unauthenticated_user_cannot_export_users_pdf(): void
    {
        $this->getJson('/api/admin/users/export/pdf')->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_export_users_pdf(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/users/export/pdf')->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_export_single_user_pdf(): void
    {
        $targetUser = User::factory()->create();
        $this->getJson('/api/admin/users/'.$targetUser->id.'/export/pdf')->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_export_single_user_pdf(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $targetUser = User::factory()->create();
        $this->getJson('/api/admin/users/'.$targetUser->id.'/export/pdf')->assertForbidden();
    }
}
