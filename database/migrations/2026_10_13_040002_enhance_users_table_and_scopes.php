<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('employee_id')->nullable()->after('user_type')->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('client_id')->nullable()->after('employee_id')->constrained('clients')->nullOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->after('client_id')->constrained('suppliers')->nullOnDelete();
            $table->foreignUlid('organisation_id')->nullable()->after('supplier_id')->constrained('organisations')->nullOnDelete();
            $table->foreignUlid('contact_id')->nullable()->after('organisation_id')->constrained('bp_contacts')->nullOnDelete();
            $table->json('access_scope')->nullable()->after('contact_id');
        });

        Schema::create('user_client_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('client_site_id')->constrained('client_sites')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'client_site_id']);
        });

        Schema::create('user_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_clients');
        Schema::dropIfExists('user_client_sites');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['client_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['organisation_id']);
            $table->dropForeign(['contact_id']);
            $table->dropColumn([
                'employee_id',
                'client_id',
                'supplier_id',
                'organisation_id',
                'contact_id',
                'access_scope',
            ]);
        });
    }
};
