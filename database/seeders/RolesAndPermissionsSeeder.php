<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed initial roles, permissions, and a Super Admin user.
     *
     * Permission naming convention: "action-subject"
     * This maps directly to CASL { action, subject } objects.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ────────────────────────────────────────────
        $permissions = [
            // Dashboard
            'read-dashboard',

            // Users management
            'read-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Roles management
            'read-roles',
            'edit-roles',

            // Permissions management
            'read-permissions',

            // Reports
            'read-reports',
            'create-reports',
            'edit-reports',
            'delete-reports',

            // Settings
            'read-settings',
            'edit-settings',

            // Sites
            'read-sites',
            'create-sites',
            'edit-sites',
            'delete-sites',

            // Organisations
            'read-organisations',
            'create-organisations',
            'edit-organisations',
            'delete-organisations',
            'export-organisations',

            // Clients / Suppliers / Service Providers
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

            // Business Partners specialized permissions
            'read-bp-dashboard',
            'read-bp-bank-accounts',
            'manage-bp-bank-accounts',
            'view-all-bp-email-batches',

            // ── Module-view permissions (Applications page) ─────────
            // Commercial & Supply
            'read-BusinessPartners',
            'read-Catalog',
            'read-catalog-items',
            'create-catalog-items',
            'edit-catalog-items',
            'delete-catalog-items',
            'read-catalog-categories',
            'create-catalog-categories',
            'edit-catalog-categories',
            'delete-catalog-categories',
            'read-catalog-brands',
            'create-catalog-brands',
            'edit-catalog-brands',
            'delete-catalog-brands',
            'read-catalog-uom',
            'create-catalog-uom',
            'edit-catalog-uom',
            'delete-catalog-uom',
            'read-catalog-equipment-classes',
            'create-catalog-equipment-classes',
            'edit-catalog-equipment-classes',
            'delete-catalog-equipment-classes',
            'read-catalog-blueprints',
            'create-catalog-blueprints',
            'edit-catalog-blueprints',
            'delete-catalog-blueprints',
            'read-catalog-guidelines',
            'create-catalog-guidelines',
            'edit-catalog-guidelines',
            'delete-catalog-guidelines',
            'read-CrmSales',
            'read-crm-quotations',
            'create-crm-quotations',
            'edit-crm-quotations',
            'delete-crm-quotations',
            'approve-crm-quotations',
            'read-crm-sales-orders',
            'create-crm-sales-orders',
            'edit-crm-sales-orders',
            'delete-crm-sales-orders',
            'approve-crm-sales-orders',
            'read-crm-contracts',
            'create-crm-contracts',
            'edit-crm-contracts',
            'delete-crm-contracts',
            'read-crm-price-requests',
            'create-crm-price-requests',
            'edit-crm-price-requests',
            'delete-crm-price-requests',
            'read-crm-price-lists',
            'create-crm-price-lists',
            'edit-crm-price-lists',
            'delete-crm-price-lists',
            'read-crm-lsa-documents',
            'read-crm-gcs-documents',
            'read-SupplyChainProcurement',
            'read-procurement-iprs',
            'create-procurement-iprs',
            'edit-procurement-iprs',
            'delete-procurement-iprs',
            'approve-procurement-iprs',
            'read-procurement-rfqs',
            'create-procurement-rfqs',
            'edit-procurement-rfqs',
            'delete-procurement-rfqs',
            'award-procurement-rfqs',
            'read-procurement-pos',
            'create-procurement-pos',
            'edit-procurement-pos',
            'delete-procurement-pos',
            'approve-procurement-pos',
            'read-procurement-grns',
            'create-procurement-grns',
            'edit-procurement-grns',
            'delete-procurement-grns',

            // Operations & Delivery
            'read-InventoryWarehouse',
            'read-inventory-stock-items',
            'read-inventory-movements',
            'create-inventory-movements',
            'read-inventory-sites',
            'create-inventory-sites',
            'edit-inventory-sites',
            'delete-inventory-sites',
            'read-inventory-zones',
            'create-inventory-zones',
            'edit-inventory-zones',
            'delete-inventory-zones',
            'read-inventory-locations',
            'create-inventory-locations',
            'edit-inventory-locations',
            'delete-inventory-locations',
            'read-inventory-lots',
            'create-inventory-lots',
            'edit-inventory-lots',
            'delete-inventory-lots',
            'read-Logistics',
            'create-Logistics',
            'edit-Logistics',
            'delete-Logistics',
            'read-delivery_work_orders',
            'create-delivery_work_orders',
            'edit-delivery_work_orders',
            'delete-delivery_work_orders',
            'read-delivery_waves',
            'create-delivery_waves',
            'edit-delivery_waves',
            'read-OperationalHub',
            'create-OperationalHub',
            'edit-OperationalHub',
            'delete-OperationalHub',
            'read-service_work_orders',
            'create-service_work_orders',
            'edit-service_work_orders',
            'delete-service_work_orders',
            'plan-service_work_orders',
            'read-missions',
            'create-missions',
            'edit-missions',
            'delete-missions',
            'execute-missions',
            'read-interventions',
            'create-interventions',
            'edit-interventions',
            'delete-interventions',
            'read-Workshop',
            'create-Workshop',
            'edit-Workshop',
            'delete-Workshop',
            'read-workshop_units',
            'create-workshop_units',
            'edit-workshop_units',
            'delete-workshop_units',
            'read-workshop_receptions',
            'create-workshop_receptions',
            'edit-workshop_receptions',
            'delete-workshop_receptions',
            'read-service_delivery_notes',
            'create-service_delivery_notes',
            'edit-service_delivery_notes',
            'delete-service_delivery_notes',
            'read-ClientAssets',

            // Support & Administration
            'read-FinancialCenter',
            'read-PeopleSkills',
            'read-ReportingAnalytics',
            'read-UserPortals',
            'read-SettingsAdministration',
            'read-UserManagement',

            // ── Client Assets Module CRUD ────────────────────────────
            'read-client-sites',
            'create-client-sites',
            'edit-client-sites',
            'delete-client-sites',
            'read-client-equipment',
            'create-client-equipment',
            'edit-client-equipment',
            'delete-client-equipment',
            'read-workwear-models',
            'create-workwear-models',
            'edit-workwear-models',
            'delete-workwear-models',
            'read-workwear-employees',
            'create-workwear-employees',
            'edit-workwear-employees',
            'delete-workwear-employees',
            'read-workwear-items',
            'create-workwear-items',
            'edit-workwear-items',
            'delete-workwear-items',

            // Communications
            'read-Communications',
            'create-email-messages',
            'edit-email-messages',
            'delete-email-messages',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roles ──────────────────────────────────────────────────

        // 1. Super Admin — catches everything via CASL wildcard
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // 2. Admin — gets all permissions
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        // 3. Responsable
        $responsableRole = Role::firstOrCreate(['name' => 'Responsable', 'guard_name' => 'web']);
        $responsableRole->syncPermissions([
            'read-dashboard',
            'read-users',
            'edit-users',
            'read-roles',
            'read-permissions',
            'read-reports',
            'create-reports',
            'edit-reports',
            'read-settings',
            'read-sites',
            'edit-sites',
        ]);

        // 4. Staff
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $staffRole->syncPermissions([
            'read-dashboard',
            'read-users',
            'read-reports',
            'read-sites',
        ]);

        // 5. Client
        $clientRole = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);
        $clientRole->syncPermissions([
            'read-dashboard',
            'read-reports',
            'read-sites',
        ]);

        // 6. Client Site Manager
        $clientSiteManagerRole = Role::firstOrCreate(['name' => 'Client Site Manager', 'guard_name' => 'web']);
        $clientSiteManagerRole->syncPermissions([
            'read-dashboard',
            'read-reports',
            'read-sites',
            'edit-sites',
        ]);

        // 7. Client Group
        $clientGroupRole = Role::firstOrCreate(['name' => 'Client Group', 'guard_name' => 'web']);
        $clientGroupRole->syncPermissions([
            'read-dashboard',
            'read-reports',
            'read-sites',
            'read-organisations',
        ]);

        // 8. Organisation
        $organisationRole = Role::firstOrCreate(['name' => 'Organisation', 'guard_name' => 'web']);
        $organisationRole->syncPermissions([
            'read-dashboard',
            'read-reports',
            'read-sites',
            'read-organisations',
            'edit-organisations',
        ]);

        // ── 9. Sales Responsible ──────────────────────────────────
        $salesRole = Role::firstOrCreate(['name' => 'sales_responsible', 'guard_name' => 'web']);
        $salesRole->syncPermissions([
            'read-dashboard',
            'read-BusinessPartners',
            'read-bp-dashboard',
            'read-clients',
            'create-clients',
            'edit-clients',
            'export-clients',
            'read-organisations',
            'read-bp-bank-accounts',
            'read-Catalog',
            'read-CrmSales',
            'read-crm-quotations',
            'create-crm-quotations',
            'edit-crm-quotations',
            'read-crm-sales-orders',
            'create-crm-sales-orders',
            'edit-crm-sales-orders',
            'read-crm-contracts',
            'create-crm-contracts',
            'edit-crm-contracts',
            'read-crm-price-requests',
            'create-crm-price-requests',
            'edit-crm-price-requests',
            'read-crm-price-lists',
            'read-crm-lsa-documents',
            'read-crm-gcs-documents',
            'read-SupplyChainProcurement',
        ]);

        // ── 10. Operations Responsible ────────────────────────────
        $opsRole = Role::firstOrCreate(['name' => 'operations_responsible', 'guard_name' => 'web']);
        $opsRole->syncPermissions([
            'read-dashboard',
            'read-InventoryWarehouse',
            'read-inventory-stock-items',
            'read-inventory-movements',
            'create-inventory-movements',
            'read-inventory-sites',
            'create-inventory-sites',
            'edit-inventory-sites',
            'delete-inventory-sites',
            'read-inventory-zones',
            'create-inventory-zones',
            'edit-inventory-zones',
            'delete-inventory-zones',
            'read-inventory-locations',
            'create-inventory-locations',
            'edit-inventory-locations',
            'delete-inventory-locations',
            'read-inventory-lots',
            'create-inventory-lots',
            'edit-inventory-lots',
            'delete-inventory-lots',
            'read-Logistics',
            'create-Logistics',
            'edit-Logistics',
            'delete-Logistics',
            'read-delivery_work_orders',
            'create-delivery_work_orders',
            'edit-delivery_work_orders',
            'delete-delivery_work_orders',
            'read-delivery_waves',
            'create-delivery_waves',
            'edit-delivery_waves',
            'read-OperationalHub',
            'create-OperationalHub',
            'edit-OperationalHub',
            'delete-OperationalHub',
            'read-service_work_orders',
            'create-service_work_orders',
            'edit-service_work_orders',
            'delete-service_work_orders',
            'plan-service_work_orders',
            'read-missions',
            'create-missions',
            'edit-missions',
            'delete-missions',
            'execute-missions',
            'read-interventions',
            'create-interventions',
            'edit-interventions',
            'delete-interventions',
            'read-Workshop',
            'create-Workshop',
            'edit-Workshop',
            'delete-Workshop',
            'read-workshop_units',
            'create-workshop_units',
            'edit-workshop_units',
            'delete-workshop_units',
            'read-workshop_receptions',
            'create-workshop_receptions',
            'edit-workshop_receptions',
            'delete-workshop_receptions',
            'read-service_delivery_notes',
            'create-service_delivery_notes',
            'edit-service_delivery_notes',
            'delete-service_delivery_notes',
            'read-ClientAssets',
            'read-client-sites',
            'create-client-sites',
            'edit-client-sites',
            'delete-client-sites',
            'read-client-equipment',
            'create-client-equipment',
            'edit-client-equipment',
            'delete-client-equipment',
            'read-workwear-models',
            'create-workwear-models',
            'edit-workwear-models',
            'delete-workwear-models',
            'read-workwear-employees',
            'create-workwear-employees',
            'edit-workwear-employees',
            'delete-workwear-employees',
            'read-workwear-items',
            'create-workwear-items',
            'edit-workwear-items',
            'delete-workwear-items',
            'read-PeopleSkills',
        ]);

        // ── 11. Financial Responsible ─────────────────────────────
        $finRole = Role::firstOrCreate(['name' => 'financial_responsible', 'guard_name' => 'web']);
        $finRole->syncPermissions([
            'read-dashboard',
            'read-FinancialCenter',
            'read-ReportingAnalytics',
        ]);

        // ── 12. Business Partner Role Templates ───────────────────
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

        foreach ($bpRoleTemplates as $roleName => $templatePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo($templatePermissions);
        }

        // Align legacy snake_case roles with BP template permissions without removing unrelated domain permissions
        $salesRole->givePermissionTo($bpRoleTemplates['Sales Responsible']);
        $finRole->givePermissionTo($bpRoleTemplates['Financial Responsible']);

        // ── Default Super Admin User ───────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@waveplus.io'],
            [
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'can_login' => true,
                'account_status' => 'active',
            ]
        );
        $admin->assignRole('Super Admin');

        // ── Default Admin User ─────────────────────────────────────
        $adminUser = User::firstOrCreate(
            ['email' => 'admin2@waveplus.io'],
            [
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'can_login' => true,
                'account_status' => 'active',
            ]
        );
        $adminUser->assignRole('Admin');

        // ── Demo users for RBAC testing ───────────────────────────
        $salesUser = User::firstOrCreate(
            ['email' => 'sales@waveplus.io'],
            [
                'name' => 'Sales Responsible',
                'first_name' => 'Sales',
                'last_name' => 'Demo',
                'password' => Hash::make('password'),
                'is_active' => true,
                'can_login' => true,
                'account_status' => 'active',
            ]
        );
        $salesUser->assignRole('sales_responsible');

        $opsUser = User::firstOrCreate(
            ['email' => 'operations@waveplus.io'],
            [
                'name' => 'Operations Responsible',
                'first_name' => 'Operations',
                'last_name' => 'Demo',
                'password' => Hash::make('password'),
                'is_active' => true,
                'can_login' => true,
                'account_status' => 'active',
            ]
        );
        $opsUser->assignRole('operations_responsible');

        $finUser = User::firstOrCreate(
            ['email' => 'finance@waveplus.io'],
            [
                'name' => 'Financial Responsible',
                'first_name' => 'Finance',
                'last_name' => 'Demo',
                'password' => Hash::make('password'),
                'is_active' => true,
                'can_login' => true,
                'account_status' => 'active',
            ]
        );
        $finUser->assignRole('financial_responsible');
    }
}
