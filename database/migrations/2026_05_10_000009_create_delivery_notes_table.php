<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('dn_number')->unique();
            $table->string('status', 40)->default('draft');
            $table->foreignUlid('dwo_id')->unique()->constrained('delivery_work_orders');
            $table->foreignUlid('client_id')->constrained();
            $table->date('delivery_date');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_title')->nullable();
            $table->longText('signature_data')->nullable(); // text/base64
            $table->timestamp('signature_at')->nullable();
            $table->timestamp('stock_posted_at')->nullable();
            $table->foreignUlid('stock_posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_overall')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignUlid('ged_document_id')->nullable();
            $table->text('client_remarks')->nullable();

            // Standard tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('workwear_items', function (Blueprint $table) {
            $table->foreignUlid('delivery_note_id')->nullable()->constrained('delivery_notes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workwear_items', function (Blueprint $table) {
            $table->dropForeign(['delivery_note_id']);
            $table->dropColumn('delivery_note_id');
        });

        Schema::dropIfExists('delivery_notes');
    }
};
