<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [
            'hr.departments.view',
            'hr.departments.manage',
            'hr.positions.view',
            'hr.positions.manage',
            'hr.teams.view',
            'hr.teams.manage',
            'hr.appraisals.view',
            'hr.appraisals.manage',
            'hr.skills.view',
            'hr.skills.manage',
            'hr.employees.view-sensitive',
            'read-hr-dashboard',
            'hr.attendance.kiosk',
        ];

        foreach ($newPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Grant all new permissions to Admin role if it exists
        $adminRole = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($newPermissions);
        }

        $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($newPermissions);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'hr.departments.view',
            'hr.departments.manage',
            'hr.positions.view',
            'hr.positions.manage',
            'hr.teams.view',
            'hr.teams.manage',
            'hr.appraisals.view',
            'hr.appraisals.manage',
            'hr.skills.view',
            'hr.skills.manage',
            'hr.employees.view-sensitive',
            'read-hr-dashboard',
            'hr.attendance.kiosk',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
