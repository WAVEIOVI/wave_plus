<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $bpRoleTemplates = [
            'BP Manager' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'create-clients',
                'edit-clients',
                'delete-clients',
                'export-clients',
                'read-suppliers',
                'create-suppliers',
                'edit-suppliers',
                'delete-suppliers',
                'export-suppliers',
                'read-service-providers',
                'create-service-providers',
                'edit-service-providers',
                'delete-service-providers',
                'export-service-providers',
                'read-organisations',
                'create-organisations',
                'edit-organisations',
                'delete-organisations',
                'export-organisations',
                'read-bp-bank-accounts',
                'manage-bp-bank-accounts',
                'create-email-messages',
                'view-all-bp-email-batches',
            ],
            'Sales Responsible' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'create-clients',
                'edit-clients',
                'delete-clients',
                'export-clients',
                'read-organisations',
                'create-email-messages',
            ],
            'Procurement Responsible' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-suppliers',
                'create-suppliers',
                'edit-suppliers',
                'delete-suppliers',
                'export-suppliers',
                'read-service-providers',
                'create-email-messages',
            ],
            'Financial Responsible' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'read-suppliers',
                'read-organisations',
                'read-bp-bank-accounts',
            ],
            'BP Viewer' => [
                'read-BusinessPartners',
                'read-bp-dashboard',
                'read-clients',
                'read-suppliers',
                'read-service-providers',
                'read-organisations',
            ],
        ];

        // Ensure all required permissions exist before assigning
        foreach ($bpRoleTemplates as $templatePermissions) {
            foreach ($templatePermissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            }
        }

        // Create or update role templates idempotently without touching user assignments
        foreach ($bpRoleTemplates as $roleName => $templatePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo($templatePermissions);
        }

        // Safely augment legacy snake_case roles if they exist in the DB without overwriting unrelated permissions
        $legacySalesRole = Role::where('name', 'sales_responsible')->where('guard_name', 'web')->first();
        if ($legacySalesRole) {
            $legacySalesRole->givePermissionTo($bpRoleTemplates['Sales Responsible']);
        }

        $legacyFinRole = Role::where('name', 'financial_responsible')->where('guard_name', 'web')->first();
        if ($legacyFinRole) {
            $legacyFinRole->givePermissionTo($bpRoleTemplates['Financial Responsible']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roleNames = [
            'BP Manager',
            'Sales Responsible',
            'Procurement Responsible',
            'Financial Responsible',
            'BP Viewer',
        ];

        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                // If no users are assigned to this role template, remove it safely
                if ($role->users()->count() === 0) {
                    $role->permissions()->detach();
                    $role->delete();
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
