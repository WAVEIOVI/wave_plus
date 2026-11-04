<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('tracker_device_id', 100)->nullable()->after('odometer_last_updated');
            $table->string('tracker_imei', 50)->nullable()->after('tracker_device_id');
            $table->string('tracker_provider', 100)->nullable()->after('tracker_imei');
            $table->date('tracker_installed_at')->nullable()->after('tracker_provider');
            $table->decimal('last_gps_latitude', 10, 7)->nullable()->after('tracker_installed_at');
            $table->decimal('last_gps_longitude', 10, 7)->nullable()->after('last_gps_latitude');
            $table->dateTime('last_gps_timestamp')->nullable()->after('last_gps_longitude');
            $table->string('last_location_address', 500)->nullable()->after('last_gps_timestamp');
            $table->decimal('last_speed_kmh', 6, 2)->nullable()->after('last_location_address');

            $table->index(['tracker_device_id', 'tracker_provider']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['tracker_device_id', 'tracker_provider']);
            $table->dropColumn([
                'tracker_device_id',
                'tracker_imei',
                'tracker_provider',
                'tracker_installed_at',
                'last_gps_latitude',
                'last_gps_longitude',
                'last_gps_timestamp',
                'last_location_address',
                'last_speed_kmh',
            ]);
        });
    }
};
