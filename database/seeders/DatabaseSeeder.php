<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DocumentSequenceSeeder::class,   // doc number counters
            CrmSeeder::class,                // clients, suppliers, service providers, orgs + all BP sub-tables
            EquipmentClassSeeder::class,
            CatalogSeeder::class,            // items, services, price lists, etc
            WarehouseSeeder::class,          // warehouses and locations
            WorkwearFabricSeeder::class,
            ClientAssetsSeeder::class,       // client sites, equipment, workwear
            HrSeeder::class,                 // HR master data & workforce foundation
            EmailSenderProfileSeeder::class, // predefined email sender identities
            EmailTemplateSeeder::class,      // predefined system-managed email templates
        ]);
    }
}
