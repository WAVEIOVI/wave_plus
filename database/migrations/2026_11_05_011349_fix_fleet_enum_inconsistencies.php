<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix Fleet Database ENUM Inconsistencies
 *
 * Aligns all ENUM values across vehicles, vehicle_assignments, vehicle_documents,
 * vehicle_maintenance_records, vehicle_fuel_records, and vehicle_checklists
 * to match controller validation and frontend options.
 *
 * Changes:
 * 1. Add 'storage' and 'other' to vehicles.custody_type
 * 2. Standardize maintenance_type to: preventive, corrective, inspection, repair, recall, other
 * 3. Standardize maintenance priority to: low, normal, medium, high, urgent, critical
 * 4. Remove 'on_hold' from maintenance status
 * 5. Fix document status: add 'pending_renewal', remove 'expiring_soon', 'renewed'
 * 6. Add 'hybrid' and 'other' to vehicle_fuel_records.fuel_type
 * 7. Standardize checklist overall_status to: passed, passed_with_notes, passed_with_issues, failed, requires_attention, pending
 *
 * NOTE: This migration uses raw SQL for MySQL/MariaDB. SQLite (used in tests)
 * stores ENUMs as TEXT with CHECK constraints and accepts any text value,
 * so the migrations will not fail in tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Skip ENUM alterations for SQLite (tests) - SQLite stores ENUM as TEXT
        if ($driver === 'sqlite') {
            return;
        }

        // 1. Fix vehicles.custody_type - add 'storage' and 'other'
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN custody_type ENUM(
            'company_site',
            'employee_home',
            'mission',
            'maintenance',
            'rental',
            'client',
            'storage',
            'other'
        ) NULL");

        // 2. Fix vehicle_maintenance_records.maintenance_type
        // Keep all existing types to preserve data, ensure controller accepts them
        DB::statement("ALTER TABLE vehicle_maintenance_records MODIFY COLUMN maintenance_type ENUM(
            'preventive',
            'corrective',
            'inspection',
            'repair',
            'overhaul',
            'warranty',
            'recall',
            'other'
        )");

        // 3. Fix vehicle_maintenance_records.priority
        // Map old values before changing ENUM
        DB::statement("UPDATE vehicle_maintenance_records SET priority = 'low' WHERE priority = 'routine'");
        DB::statement("UPDATE vehicle_maintenance_records SET priority = 'normal' WHERE priority NOT IN ('low', 'normal', 'medium', 'high', 'urgent', 'critical')");
        
        DB::statement("ALTER TABLE vehicle_maintenance_records MODIFY COLUMN priority ENUM(
            'low',
            'normal',
            'medium',
            'high',
            'urgent',
            'critical'
        ) DEFAULT 'normal'");

        // 4. Fix vehicle_maintenance_records.status - remove 'on_hold'
        // Map on_hold to scheduled before changing ENUM
        DB::statement("UPDATE vehicle_maintenance_records SET status = 'scheduled' WHERE status = 'on_hold'");
        
        DB::statement("ALTER TABLE vehicle_maintenance_records MODIFY COLUMN status ENUM(
            'scheduled',
            'in_progress',
            'completed',
            'cancelled'
        ) DEFAULT 'scheduled'");

        // 5. Fix vehicle_documents.status
        // Map old statuses to new ones
        DB::statement("UPDATE vehicle_documents SET status = 'pending_renewal' WHERE status = 'expiring_soon'");
        DB::statement("UPDATE vehicle_documents SET status = 'active' WHERE status = 'renewed'");
        DB::statement("UPDATE vehicle_documents SET status = 'archived' WHERE status = 'cancelled'");
        
        DB::statement("ALTER TABLE vehicle_documents MODIFY COLUMN status ENUM(
            'active',
            'expired',
            'pending_renewal',
            'archived'
        ) DEFAULT 'active'");

        // 6. Fix vehicle_fuel_records.fuel_type - add 'hybrid' and 'other'
        DB::statement("ALTER TABLE vehicle_fuel_records MODIFY COLUMN fuel_type ENUM(
            'petrol',
            'diesel',
            'electric',
            'hybrid',
            'lpg',
            'cng',
            'hydrogen',
            'other'
        )");

        // 7. Fix vehicle_checklists.overall_status
        // Keep both old and new options for compatibility
        DB::statement("ALTER TABLE vehicle_checklists MODIFY COLUMN overall_status ENUM(
            'passed',
            'passed_with_notes',
            'passed_with_issues',
            'failed',
            'requires_attention',
            'pending',
            'incomplete'
        )");
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Skip ENUM alterations for SQLite (tests)
        if ($driver === 'sqlite') {
            return;
        }

        // Revert vehicles.custody_type
        DB::statement("UPDATE vehicles SET custody_type = 'company_site' WHERE custody_type IN ('storage', 'other')");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN custody_type ENUM(
            'company_site',
            'employee_home',
            'mission',
            'maintenance',
            'rental',
            'client'
        ) NULL");

        // Revert vehicle_maintenance_records.maintenance_type
        DB::statement("UPDATE vehicle_maintenance_records SET maintenance_type = 'preventive' WHERE maintenance_type = 'other'");
        DB::statement("ALTER TABLE vehicle_maintenance_records MODIFY COLUMN maintenance_type ENUM(
            'preventive',
            'corrective',
            'inspection',
            'repair',
            'overhaul',
            'warranty',
            'recall'
        )");

        // Revert vehicle_maintenance_records.priority
        DB::statement("UPDATE vehicle_maintenance_records SET priority = 'routine' WHERE priority = 'low'");
        DB::statement("UPDATE vehicle_maintenance_records SET priority = 'normal' WHERE priority IN ('medium', 'normal')");
        DB::statement("ALTER TABLE vehicle_maintenance_records MODIFY COLUMN priority ENUM(
            'routine',
            'normal',
            'high',
            'urgent',
            'critical'
        ) DEFAULT 'normal'");

        // Revert vehicle_maintenance_records.status
        DB::statement("ALTER TABLE vehicle_maintenance_records MODIFY COLUMN status ENUM(
            'scheduled',
            'in_progress',
            'completed',
            'cancelled',
            'on_hold'
        ) DEFAULT 'scheduled'");

        // Revert vehicle_documents.status
        DB::statement("UPDATE vehicle_documents SET status = 'expiring_soon' WHERE status = 'pending_renewal'");
        DB::statement("ALTER TABLE vehicle_documents MODIFY COLUMN status ENUM(
            'active',
            'expired',
            'expiring_soon',
            'renewed',
            'cancelled',
            'archived'
        ) DEFAULT 'active'");

        // Revert vehicle_fuel_records.fuel_type
        DB::statement("UPDATE vehicle_fuel_records SET fuel_type = 'petrol' WHERE fuel_type IN ('hybrid', 'other')");
        DB::statement("ALTER TABLE vehicle_fuel_records MODIFY COLUMN fuel_type ENUM(
            'petrol',
            'diesel',
            'electric',
            'lpg',
            'cng',
            'hydrogen',
            'other'
        )");

        // Revert vehicle_checklists.overall_status
        DB::statement("UPDATE vehicle_checklists SET overall_status = 'passed_with_notes' WHERE overall_status = 'passed_with_issues'");
        DB::statement("UPDATE vehicle_checklists SET overall_status = 'requires_attention' WHERE overall_status = 'pending'");
        DB::statement("ALTER TABLE vehicle_checklists MODIFY COLUMN overall_status ENUM(
            'passed',
            'passed_with_notes',
            'failed',
            'requires_attention',
            'incomplete'
        )");
    }
};
