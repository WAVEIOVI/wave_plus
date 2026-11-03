<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->enum('source', ['phone', 'email', 'in_person', 'portal', 'tender', 'referral']);
            $table->timestamp('received_at');
            $table->date('response_deadline')->nullable();
            $table->string('client_reference', 100)->nullable();
            $table->ulid('site_id')->nullable();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['new', 'pending_internal', 'costing', 'responded', 'converted_to_quote', 'cancelled'])->default('new');
            $table->text('client_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->ulid('converted_to_quotation_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_requests');
    }
};
