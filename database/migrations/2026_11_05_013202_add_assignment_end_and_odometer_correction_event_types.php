<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add missing event types to vehicle_activity_log table.
 *
 * Adds:
 * - assignment_end: for tracking when assignments are completed/returned
 * - odometer_correction: for tracking authorized odometer corrections
 * - archived: for tracking vehicle archival
 * - deleted: for tracking vehicle deletion
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // MySQL: ALTER the enum to add new values
            DB::statement("ALTER TABLE vehicle_activity_log MODIFY COLUMN event_type ENUM(
                'created',
                'status_change',
                'assignment',
                'assignment_end',
                'reassignment',
                'fuel',
                'maintenance',
                'inspection',
                'checklist',
                'incident',
                'document_update',
                'rental_start',
                'rental_end',
                'client_use_start',
                'client_use_end',
                'mission_start',
                'mission_end',
                'location_change',
                'odometer_update',
                'odometer_correction',
                'archived',
                'deleted',
                'other'
            ) NOT NULL");
        }

        // SQLite: ENUMs are implemented as CHECK constraints, which cannot be altered.
        // For SQLite, we rely on the base migration being updated for fresh installs.
        // Existing SQLite databases (if any) would need to be recreated or manually handled.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Remove the added event types (if rolling back)
            DB::statement("ALTER TABLE vehicle_activity_log MODIFY COLUMN event_type ENUM(
                'created',
                'status_change',
                'assignment',
                'reassignment',
                'fuel',
                'maintenance',
                'inspection',
                'checklist',
                'incident',
                'document_update',
                'rental_start',
                'rental_end',
                'client_use_start',
                'client_use_end',
                'mission_start',
                'mission_end',
                'location_change',
                'odometer_update',
                'other'
            ) NOT NULL");
        }
    }
};
