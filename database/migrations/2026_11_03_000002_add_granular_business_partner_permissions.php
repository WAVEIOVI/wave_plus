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
            'export-clients',
            'export-suppliers',
            'export-service-providers',
            'export-organisations',
            'read-bp-dashboard',
            'read-bp-bank-accounts',
            'manage-bp-bank-accounts',
            'view-all-bp-email-batches',
        ];

        foreach ($newPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Grant to Admin role if it exists
        $adminRole = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($newPermissions);
        }

        // Migrate sales_responsible if it exists
        $salesRole = Role::where('name', 'sales_responsible')->where('guard_name', 'web')->first();
        if ($salesRole) {
            $salesRole->givePermissionTo([
                'read-clients',
                'create-clients',
                'edit-clients',
                'export-clients',
                'read-organisations',
                'read-bp-dashboard',
                'read-bp-bank-accounts',
            ]);
        }

        // For any existing role that has edit-clients or read-clients, grant export-clients safely
        $rolesWithClients = Role::whereHas('permissions', function ($q) {
            $q->whereIn('name', ['edit-clients', 'create-clients']);
        })->get();

        foreach ($rolesWithClients as $role) {
            if (! in_array($role->name, ['Super Admin', 'Admin'], true)) {
                $role->givePermissionTo(['export-clients', 'read-bp-dashboard']);
            }
        }

        $rolesWithSuppliers = Role::whereHas('permissions', function ($q) {
            $q->whereIn('name', ['edit-suppliers', 'create-suppliers']);
        })->get();

        foreach ($rolesWithSuppliers as $role) {
            if (! in_array($role->name, ['Super Admin', 'Admin'], true)) {
                $role->givePermissionTo(['export-suppliers', 'read-bp-dashboard']);
            }
        }

        $rolesWithOrganisations = Role::whereHas('permissions', function ($q) {
            $q->whereIn('name', ['edit-organisations', 'create-organisations']);
        })->get();

        foreach ($rolesWithOrganisations as $role) {
            if (! in_array($role->name, ['Super Admin', 'Admin'], true)) {
                $role->givePermissionTo(['export-organisations']);
            }
        }

        $rolesWithServiceProviders = Role::whereHas('permissions', function ($q) {
            $q->whereIn('name', ['edit-service-providers', 'create-service-providers']);
        })->get();

        foreach ($rolesWithServiceProviders as $role) {
            if (! in_array($role->name, ['Super Admin', 'Admin'], true)) {
                $role->givePermissionTo(['export-service-providers', 'read-bp-dashboard']);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [
            'export-clients',
            'export-suppliers',
            'export-service-providers',
            'export-organisations',
            'read-bp-dashboard',
            'read-bp-bank-accounts',
            'manage-bp-bank-accounts',
            'view-all-bp-email-batches',
        ];

        Permission::whereIn('name', $newPermissions)->where('guard_name', 'web')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
