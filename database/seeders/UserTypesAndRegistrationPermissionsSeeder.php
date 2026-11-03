<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class UserTypesAndRegistrationPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure standard roles exist
        $roles = [
            'Super Admin',
            'Admin',
            'Employee',
            'Client',
            'Client Site Manager',
            'Client Group',
            'Organisation',
            'Supplier',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Permissions for registration requests
        $permissions = [
            'read-registration-requests',
            'create-registration-requests',
            'update-registration-requests',
            'delete-registration-requests',
            'approve-registration-requests',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // Assign to Admin and Super Admin
        $admin = Role::where('name', 'Admin')->first();
        $superAdmin = Role::where('name', 'Super Admin')->first();

        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }
    }
}
