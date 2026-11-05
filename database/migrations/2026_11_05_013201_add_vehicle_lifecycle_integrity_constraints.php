<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add Vehicle Lifecycle Integrity Constraints
 *
 * Ensures:
 * 1. Only one current assignment per vehicle (unique constraint)
 * 2. Odometer tracking and audit trail
 * 3. Safe deletion protection flags
 * 4. Assignment date validation
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // 1. Ensure only one is_current=true per vehicle before adding constraint
        if ($driver === 'sqlite') {
            // SQLite: Use simpler approach
            $duplicates = DB::select("
                SELECT vehicle_id, COUNT(*) as cnt
                FROM vehicle_assignments
                WHERE is_current = 1
                GROUP BY vehicle_id
                HAVING cnt > 1
            ");

            foreach ($duplicates as $dup) {
                // Keep only the most recent current assignment
                DB::statement("
                    UPDATE vehicle_assignments
                    SET is_current = 0
                    WHERE vehicle_id = ?
                    AND is_current = 1
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM vehicle_assignments
                            WHERE vehicle_id = ? AND is_current = 1
                            ORDER BY created_at DESC
                            LIMIT 1
                        ) tmp
                    )
                ", [$dup->vehicle_id, $dup->vehicle_id]);
            }
        } elseif ($driver === 'mysql') {
            // MySQL: Use JOIN approach
            DB::statement("
                UPDATE vehicle_assignments va1
                INNER JOIN (
                    SELECT vehicle_id, MAX(created_at) as max_created
                    FROM vehicle_assignments
                    WHERE is_current = true
                    GROUP BY vehicle_id
                    HAVING COUNT(*) > 1
                ) va2 ON va1.vehicle_id = va2.vehicle_id
                SET va1.is_current = false
                WHERE va1.is_current = true
                AND va1.created_at < va2.max_created
            ");
        }

        // 2. Add unique partial index for current assignments (MySQL 8.0+)
        if ($driver === 'mysql') {
            // MySQL doesn't support partial indexes directly, use filtered index via stored column
            Schema::table('vehicle_assignments', function (Blueprint $table) {
                // Add a helper column for unique constraint
                $table->string('unique_current_key', 50)->nullable()->after('is_current');
            });

            DB::statement("
                UPDATE vehicle_assignments
                SET unique_current_key = CONCAT('current_', vehicle_id)
                WHERE is_current = true
            ");

            Schema::table('vehicle_assignments', function (Blueprint $table) {
                $table->unique('unique_current_key', 'unique_current_assignment');
            });
        } elseif ($driver === 'sqlite') {
            // SQLite: partial unique index
            DB::statement("
                CREATE UNIQUE INDEX unique_current_assignment
                ON vehicle_assignments (vehicle_id)
                WHERE is_current = 1
            ");
        }

        // 3. Add odometer audit columns to vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('odometer_history')->nullable()->after('odometer_last_updated');
            $table->boolean('allow_odometer_decrease')->default(false)->after('odometer_history');
        });

        // 4. Add deletion protection flags
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('has_operational_history')->default(false)->after('status');
            $table->timestamp('archived_at')->nullable()->after('deleted_at');
            $table->foreignUlid('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });

        // 5. Add check: end_date must be >= start_date (MySQL only)
        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE vehicle_assignments
                ADD CONSTRAINT chk_assignment_dates
                CHECK (end_date IS NULL OR end_date >= start_date)
            ");
        }

        // 6. Initialize operational history flag for existing vehicles with assignments
        DB::statement("
            UPDATE vehicles
            SET has_operational_history = true
            WHERE id IN (
                SELECT DISTINCT vehicle_id FROM vehicle_assignments
            )
        ");

        // 7. Add index for active assignments query optimization
        Schema::table('vehicle_assignments', function (Blueprint $table) {
            $table->index(['vehicle_id', 'is_current', 'start_date'], 'idx_vehicle_current_assignments');
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Drop indexes and constraints
        if ($driver === 'mysql') {
            Schema::table('vehicle_assignments', function (Blueprint $table) {
                $table->dropUnique('unique_current_assignment');
                $table->dropColumn('unique_current_key');
            });

            try {
                DB::statement("ALTER TABLE vehicle_assignments DROP CONSTRAINT chk_assignment_dates");
            } catch (\Exception $e) {
                // Constraint might not exist
            }
        } elseif ($driver === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS unique_current_assignment");
        }

        Schema::table('vehicle_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_vehicle_current_assignments');
        });

        // Remove columns from vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropColumn([
                'odometer_history',
                'allow_odometer_decrease',
                'has_operational_history',
                'archived_at',
                'archived_by',
            ]);
        });
    }
};
