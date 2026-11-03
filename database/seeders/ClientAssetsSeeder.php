<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientEquipmentTransfer;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use App\Modules\ClientAssets\Models\EquipmentComplianceRecord;
use App\Modules\ClientAssets\Models\EquipmentInterventionCycle;
use App\Modules\ClientAssets\Models\EquipmentLocationHistory;
use App\Modules\ClientAssets\Models\EquipmentOptionValue;
use App\Modules\ClientAssets\Models\EquipmentQrCode;
use App\Modules\ClientAssets\Models\EquipmentStatusHistory;
use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Models\WorkwearModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientAssetsSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::first();
        if (! $client) {
            $this->command->warn('No clients found — skipping ClientAssetsSeeder.');

            return;
        }

        // Clean up previous client assets test data for idempotency
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('equipment_compliance_records')->truncate();
        DB::table('equipment_option_values')->truncate();
        DB::table('equipment_status_histories')->truncate();
        DB::table('equipment_location_histories')->truncate();
        DB::table('equipment_intervention_cycles')->truncate();
        DB::table('equipment_qr_codes')->truncate();
        DB::table('client_equipment_transfers')->truncate();
        DB::table('client_equipment')->truncate();
        DB::table('client_site_areas')->truncate();
        DB::table('client_sites')->truncate();
        DB::table('workwear_items')->truncate();
        DB::table('workwear_employees')->truncate();
        DB::table('workwear_models')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // Find or create address for client
        $address = BpAddress::where('addressable_type', Client::class)
            ->where('addressable_id', $client->id)
            ->first();

        if (! $address) {
            $address = BpAddress::create([
                'addressable_type' => Client::class,
                'addressable_id' => $client->id,
                'type' => 'operational_site',
                'is_primary' => true,
                'address_street' => ['en' => '15 Avenue Habib Bourguiba', 'fr' => '15 Avenue Habib Bourguiba'],
                'city' => ['en' => 'Tunis', 'fr' => 'Tunis'],
                'country' => 'Tunisia',
                'postcode' => '1001',
                'phone' => '+216 71 100 200',
                'email' => 'site1@client.tn',
            ]);
        }

        $address2 = BpAddress::where('addressable_type', Client::class)
            ->where('addressable_id', $client->id)
            ->where('id', '!=', $address->id)
            ->first();

        if (! $address2) {
            $address2 = BpAddress::create([
                'addressable_type' => Client::class,
                'addressable_id' => $client->id,
                'type' => 'warehouse',
                'is_primary' => false,
                'address_street' => ['en' => 'Zone Industrielle Poudriere II', 'fr' => 'Zone Industrielle Poudriere II'],
                'city' => ['en' => 'Sfax', 'fr' => 'Sfax'],
                'country' => 'Tunisia',
                'postcode' => '3000',
                'phone' => '+216 74 400 500',
                'email' => 'sfax.plant@client.tn',
            ]);
        }

        // ── 1. Client Sites ──────────────────────────────────────────
        $site1 = ClientSite::create([
            'client_id' => $client->id,
            'bp_address_id' => $address->id,
            'site_code' => 'CS-TUNIS-HQ-01',
            'name' => 'Tunis HQ Campus & Administrative Complex',
            'description' => 'Primary corporate headquarters including management offices, server rooms and customer lounge.',
            'site_type' => 'office',
            'status' => 'active',
            'risk_class' => 'low',
            'operating_hours' => 'Mon-Fri: 08:00 - 18:00',
            'access_instructions' => 'Visitor badge required at main reception gate.',
            'safety_instructions' => 'Assembly point located at North Parking.',
            'special_instructions' => 'Server room requires Tier 3 biometric authorization.',
            'opened_at' => now()->subYears(5),
            'notes' => 'Main HQ campus.',
        ]);

        $site2 = ClientSite::create([
            'client_id' => $client->id,
            'bp_address_id' => $address2->id,
            'site_code' => 'CS-SFAX-PLANT-02',
            'name' => 'Sfax Industrial Manufacturing Plant',
            'description' => 'Heavy industrial manufacturing and chemical storage facility.',
            'site_type' => 'factory',
            'status' => 'active',
            'risk_class' => 'high',
            'operating_hours' => '24/7 3-Shift Operation',
            'access_instructions' => 'Mandatory PPE (helmet, safety shoes, high-vis jacket) at entry.',
            'safety_instructions' => 'Hazardous materials on site. Strict no-smoking policy.',
            'opened_at' => now()->subYears(3),
            'notes' => 'Industrial site with ATEX zones.',
        ]);

        // ── 2. Recursive Area Hierarchy ──────────────────────────────
        // Site 1:
        // Building A
        //  ├── Floor 1
        //  │    ├── Zone A
        //  │    └── Zone B
        //  └── Floor 2
        //       └── Server Room
        $bldgA = ClientSiteArea::create([
            'site_id' => $site1->id,
            'code' => 'BLDG-A',
            'name' => 'Building A (Main Tower)',
            'area_type' => 'building',
            'level_number' => 0,
            'status' => 'active',
            'sort_order' => 1,
            'description' => '5-Story Main Administration Tower',
        ]);

        $floor1 = ClientSiteArea::create([
            'site_id' => $site1->id,
            'parent_area_id' => $bldgA->id,
            'code' => 'FL-01',
            'name' => 'Floor 1 (Ground Floor)',
            'area_type' => 'floor',
            'level_number' => 1,
            'status' => 'active',
            'sort_order' => 1,
            'description' => 'Reception, cafeteria and meeting rooms',
        ]);

        $zoneA = ClientSiteArea::create([
            'site_id' => $site1->id,
            'parent_area_id' => $floor1->id,
            'code' => 'Z-01-A',
            'name' => 'Zone A (Main Lobby & Reception)',
            'area_type' => 'zone',
            'level_number' => 1,
            'status' => 'active',
            'sort_order' => 1,
            'description' => 'Main public entrance area',
        ]);

        $zoneB = ClientSiteArea::create([
            'site_id' => $site1->id,
            'parent_area_id' => $floor1->id,
            'code' => 'Z-01-B',
            'name' => 'Zone B (West Wing Cafeteria)',
            'area_type' => 'zone',
            'level_number' => 1,
            'status' => 'active',
            'sort_order' => 2,
            'description' => 'Staff dining and kitchen area',
        ]);

        $floor2 = ClientSiteArea::create([
            'site_id' => $site1->id,
            'parent_area_id' => $bldgA->id,
            'code' => 'FL-02',
            'name' => 'Floor 2 (IT & Infrastructure)',
            'area_type' => 'floor',
            'level_number' => 2,
            'status' => 'active',
            'sort_order' => 2,
            'description' => 'Data center and network operations center',
        ]);

        $serverRoom = ClientSiteArea::create([
            'site_id' => $site1->id,
            'parent_area_id' => $floor2->id,
            'code' => 'SR-02',
            'name' => 'Primary Server & Telecom Room',
            'area_type' => 'room',
            'level_number' => 2,
            'status' => 'active',
            'sort_order' => 1,
            'description' => 'Air-conditioned cleanroom with FM200 suppression',
        ]);

        // Site 2: Sfax Plant Areas
        $prodHall = ClientSiteArea::create([
            'site_id' => $site2->id,
            'code' => 'PROD-HALL-A',
            'name' => 'Production Hall A (Assembly)',
            'area_type' => 'workshop',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $chemWarehouse = ClientSiteArea::create([
            'site_id' => $site2->id,
            'code' => 'WH-CHEM-01',
            'name' => 'Chemical Warehouse Wing',
            'area_type' => 'warehouse',
            'status' => 'active',
            'sort_order' => 2,
        ]);

        // ── 3. Equipment Scenarios ───────────────────────────────────
        $extClass = EquipmentClass::where('code', 'portable_fire_extinguisher')->first();
        $panelClass = EquipmentClass::where('code', 'fire_alarm_panel')->first();
        $hoseClass = EquipmentClass::where('code', 'fire_hose_reel')->first();

        $extBp = EquipmentBlueprint::with('blueprintOptions.values', 'interventionCycles')
            ->where('code', 'like', '%EXT%')
            ->first() ?: EquipmentBlueprint::first();

        $panelBp = EquipmentBlueprint::with('blueprintOptions.values', 'interventionCycles')
            ->where('code', 'like', '%CDI%')
            ->first() ?: $extBp;

        $now = now();

        // ── Scenario A: Equipment with Site + Nested Area ─────────────
        $eq1 = ClientEquipment::create([
            'equipment_code' => 'EQ-260904-0001',
            'uuid' => 'EQ-QR-0001-A',
            'client_id' => $client->id,
            'site_id' => $site1->id,
            'area_id' => $zoneA->id,
            'equipment_class_id' => $extClass?->id,
            'blueprint_id' => $extBp?->id,
            'brand' => 'Gloria',
            'model_name' => 'PDE 6 PRO',
            'serial_number' => 'SN-GL-2024-8891',
            'internal_number' => 'INT-LOBBY-01',
            'asset_tag' => 'TAG-HQ-001',
            'manufacture_date' => now()->subYears(2),
            'install_date' => now()->subYears(1),
            'commissioning_date' => now()->subYears(1),
            'warranty_start_date' => now()->subYears(1),
            'warranty_end_date' => now()->addYears(2),
            'last_inspection_date' => now()->subMonths(6),
            'next_inspection_date' => now()->addMonths(6),
            'inspection_freq_months' => 12,
            'status' => 'active',
            'lifecycle_status' => 'operational',
            'condition_status' => 'excellent',
            'criticality' => 'high',
            'description' => 'Dry powder extinguisher mounted at reception entrance.',
        ]);

        EquipmentQrCode::create([
            'equipment_id' => $eq1->id,
            'qr_code' => 'EQ-QR-0001-A',
            'is_current' => true,
            'assigned_at' => $now->subYears(1),
            'reason' => 'Initial commissioning QR',
        ]);

        EquipmentStatusHistory::create([
            'equipment_id' => $eq1->id,
            'status' => 'operational',
            'started_at' => $now->subYears(1),
            'reason' => 'Commissioned in lobby',
        ]);

        EquipmentLocationHistory::create([
            'equipment_id' => $eq1->id,
            'client_id' => $client->id,
            'site_id' => $site1->id,
            'area_id' => $zoneA->id,
            'started_at' => $now->subYears(1),
            'movement_type' => 'initial_placement',
            'reason' => 'Installed at lobby',
        ]);

        EquipmentInterventionCycle::create([
            'equipment_id' => $eq1->id,
            'cycle_type' => 'Annual Inspection',
            'interval_value' => 12,
            'interval_unit' => 'months',
            'last_intervention_date' => $now->subMonths(6)->toDateString(),
            'next_due_date' => $now->addMonths(6)->toDateString(),
            'enabled' => true,
            'notes' => 'Standard yearly visual check & pressure gauge verification',
        ]);

        EquipmentInterventionCycle::create([
            'equipment_id' => $eq1->id,
            'cycle_type' => 'Hydrostatic Test',
            'interval_value' => 60,
            'interval_unit' => 'months',
            'last_intervention_date' => $now->subYears(2)->toDateString(),
            'next_due_date' => $now->addYears(3)->toDateString(),
            'enabled' => true,
            'notes' => '5-year pressure vessel hydrostatic proof test',
        ]);

        // Snapshot options
        EquipmentOptionValue::create([
            'equipment_id' => $eq1->id,
            'blueprint_option_id' => $extBp?->blueprintOptions?->first()?->id ?? (string) Str::ulid(),
            'option_name' => 'Capacity',
            'value_text' => '6 kg',
        ]);
        EquipmentOptionValue::create([
            'equipment_id' => $eq1->id,
            'blueprint_option_id' => $extBp?->blueprintOptions?->get(1)?->id ?? (string) Str::ulid(),
            'option_name' => 'Pressure Type',
            'value_text' => 'Stored Pressure',
        ]);

        // ── Scenario B: Equipment with Site Only (No Area) ────────────
        $eq2 = ClientEquipment::create([
            'equipment_code' => 'EQ-260904-0002',
            'uuid' => 'EQ-QR-0002-A',
            'client_id' => $client->id,
            'site_id' => $site2->id,
            'area_id' => null,
            'equipment_class_id' => $panelClass?->id,
            'blueprint_id' => $panelBp?->id,
            'brand' => 'Notifier',
            'model_name' => 'NFS2-3030',
            'serial_number' => 'SN-NOTIF-9021',
            'internal_number' => 'INT-SFAX-PANEL-01',
            'asset_tag' => 'TAG-SFAX-P01',
            'manufacture_date' => now()->subYears(1),
            'install_date' => now()->subMonths(6),
            'last_inspection_date' => now()->subMonths(6),
            'next_inspection_date' => now()->addMonths(6),
            'inspection_freq_months' => 6,
            'status' => 'active',
            'lifecycle_status' => 'operational',
            'condition_status' => 'excellent',
            'criticality' => 'critical',
            'description' => 'Master fire detection panel at Sfax plant guardhouse.',
        ]);

        EquipmentQrCode::create([
            'equipment_id' => $eq2->id,
            'qr_code' => 'EQ-QR-0002-A',
            'is_current' => true,
            'assigned_at' => $now->subMonths(6),
            'reason' => 'Plant commission',
        ]);

        EquipmentLocationHistory::create([
            'equipment_id' => $eq2->id,
            'client_id' => $client->id,
            'site_id' => $site2->id,
            'area_id' => null,
            'started_at' => $now->subMonths(6),
            'movement_type' => 'initial_placement',
            'reason' => 'Installed at plant entry gate',
        ]);

        // ── Scenario C: Equipment without Location (In Storage) ───────
        $eq3 = ClientEquipment::create([
            'equipment_code' => 'EQ-260904-0003',
            'uuid' => 'EQ-QR-0003-A',
            'client_id' => $client->id,
            'site_id' => null,
            'area_id' => null,
            'equipment_class_id' => $extClass?->id,
            'blueprint_id' => $extBp?->id,
            'brand' => 'Ansul',
            'model_name' => 'Sentry ABC 9KG',
            'serial_number' => 'SN-AN-5542',
            'internal_number' => 'INT-STOCK-03',
            'status' => 'active',
            'lifecycle_status' => 'stored',
            'condition_status' => 'good',
            'criticality' => 'low',
            'description' => 'Spare unit held in central client reserve stock.',
        ]);

        EquipmentQrCode::create([
            'equipment_id' => $eq3->id,
            'qr_code' => 'EQ-QR-0003-A',
            'is_current' => true,
            'assigned_at' => $now,
            'reason' => 'New reserve unit tag',
        ]);

        EquipmentStatusHistory::create([
            'equipment_id' => $eq3->id,
            'status' => 'stored',
            'started_at' => $now,
            'reason' => 'Received in reserve warehouse',
        ]);

        // ── Scenario D: Equipment Moved with Location Transfer History ─
        $eq4 = ClientEquipment::create([
            'equipment_code' => 'EQ-260904-0004',
            'uuid' => 'EQ-QR-0004-A',
            'client_id' => $client->id,
            'site_id' => $site1->id,
            'area_id' => $serverRoom->id,
            'equipment_class_id' => $extClass?->id,
            'blueprint_id' => $extBp?->id,
            'brand' => 'Kidde',
            'model_name' => 'CO2 Carbon Dioxide 5KG',
            'serial_number' => 'SN-KD-4401',
            'internal_number' => 'INT-SRV-CO2-01',
            'manufacture_date' => now()->subYears(3),
            'install_date' => now()->subYears(2),
            'status' => 'active',
            'lifecycle_status' => 'operational',
            'condition_status' => 'good',
            'criticality' => 'critical',
            'description' => 'CO2 clean-agent extinguisher transferred from Cafeteria to Server Room.',
        ]);

        EquipmentQrCode::create([
            'equipment_id' => $eq4->id,
            'qr_code' => 'EQ-QR-0004-A',
            'is_current' => true,
            'assigned_at' => $now->subYears(2),
            'reason' => 'Initial QR',
        ]);

        // Previous location record (ended)
        EquipmentLocationHistory::create([
            'equipment_id' => $eq4->id,
            'client_id' => $client->id,
            'site_id' => $site1->id,
            'area_id' => $zoneB->id,
            'started_at' => $now->subYears(2),
            'ended_at' => $now->subMonths(3),
            'movement_type' => 'initial_placement',
            'reason' => 'Initial deployment in Cafeteria',
        ]);

        // Current location record (active)
        EquipmentLocationHistory::create([
            'equipment_id' => $eq4->id,
            'client_id' => $client->id,
            'site_id' => $site1->id,
            'area_id' => $serverRoom->id,
            'started_at' => $now->subMonths(3),
            'ended_at' => null,
            'movement_type' => 'transfer',
            'reason' => 'Relocated to Server Room for electrical hazard protection',
        ]);

        ClientEquipmentTransfer::create([
            'equipment_id' => $eq4->id,
            'from_site_id' => $site1->id,
            'from_area_id' => $zoneB->id,
            'to_site_id' => $site1->id,
            'to_area_id' => $serverRoom->id,
            'transfer_date' => $now->subMonths(3),
            'reason' => 'Relocated to Server Room for electrical hazard protection',
            'condition_at_transfer' => 'good',
        ]);

        // ── Scenario E: Equipment with Replaced QR Code History ────────
        $eq5 = ClientEquipment::create([
            'equipment_code' => 'EQ-260904-0005',
            'uuid' => 'EQ-QR-0005-B', // Current active QR
            'client_id' => $client->id,
            'site_id' => $site2->id,
            'area_id' => $chemWarehouse->id,
            'equipment_class_id' => $hoseClass?->id ?? $extClass?->id,
            'blueprint_id' => $extBp?->id,
            'brand' => 'Desautel',
            'model_name' => 'RIA DN25 30M',
            'serial_number' => 'SN-DS-9932',
            'internal_number' => 'INT-CHEM-RIA-01',
            'status' => 'active',
            'lifecycle_status' => 'operational',
            'condition_status' => 'fair',
            'criticality' => 'high',
            'description' => 'Chemical warehouse unit with re-tagged QR barcode after label damage.',
        ]);

        // Old archived QR code
        EquipmentQrCode::create([
            'equipment_id' => $eq5->id,
            'qr_code' => 'EQ-QR-0005-A-OLD',
            'is_current' => false,
            'assigned_at' => $now->subYears(2),
            'unassigned_at' => $now->subMonths(1),
            'reason' => 'Damaged barcode tag during chemical washdown',
            'notes' => 'Original plastic label degraded; replaced with metal engraved tag',
        ]);

        // New current QR code
        EquipmentQrCode::create([
            'equipment_id' => $eq5->id,
            'qr_code' => 'EQ-QR-0005-B',
            'is_current' => true,
            'assigned_at' => $now->subMonths(1),
            'reason' => 'Engraved metallic replacement tag installed',
        ]);

        EquipmentLocationHistory::create([
            'equipment_id' => $eq5->id,
            'client_id' => $client->id,
            'site_id' => $site2->id,
            'area_id' => $chemWarehouse->id,
            'started_at' => $now->subYears(2),
            'movement_type' => 'initial_placement',
            'reason' => 'Installed at chemical warehouse bay 3',
        ]);

        // Compliance record
        EquipmentComplianceRecord::create([
            'equipment_id' => $eq5->id,
            'compliance_type' => 'Pressure Equipment Directive (PED 2014/68/EU)',
            'status' => 'compliant',
            'valid_from' => $now->subYears(1)->toDateString(),
            'valid_until' => $now->addYears(4)->toDateString(),
            'reference_number' => 'CERT-PED-2024-TN-991',
            'notes' => 'Bureau Veritas certification audit approved',
        ]);

        // ── 4. Workwear Models & Employees ───────────────────────────
        $wm1 = WorkwearModel::create([
            'client_id' => $client->id,
            'name' => 'Standard Hi-Vis Reflective Jacket',
            'garment_types' => ['jacket'],
            'fabric' => 'Cotton/Polyester blend (300 g/m²)',
            'colors' => ['fluorescent_orange', 'navy_blue'],
            'print_type' => 'embroidery',
            'logo_placement' => 'left_chest_and_back',
            'en_standards' => 'EN ISO 20471 Class 3',
            'status' => 'active',
        ]);

        $emp1 = WorkwearEmployee::create([
            'client_id' => $client->id,
            'employee_ref' => 'EMP-0001',
            'first_name' => 'Mohamed',
            'last_name' => 'Bouazizi',
            'department' => 'Safety & Maintenance',
            'role' => 'Lead Safety Officer',
            'status' => 'active',
            'sizes' => ['jacket' => 'L', 'pants' => 'L', 'shoes' => '43'],
        ]);

        WorkwearItem::create([
            'model_id' => $wm1->id,
            'client_id' => $client->id,
            'garment_type' => 'jacket',
            'size' => 'L',
            'status' => 'assigned',
            'item_condition' => 'good',
            'unit_cost' => 85.500,
        ]);

    }
}
