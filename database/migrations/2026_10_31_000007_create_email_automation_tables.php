<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. email_automation_rules ──────────────────────────────────────
        if (! Schema::hasTable('email_automation_rules')) {
            Schema::create('email_automation_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 150);
                $table->string('slug', 150)->unique();
                $table->text('description')->nullable();
                $table->string('trigger', 50)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->string('execution_mode', 30)->default('automatic')->index(); // automatic, approval_required, manual
                $table->string('template_slug', 100)->nullable();
                $table->unsignedBigInteger('sender_profile_id')->nullable();
                $table->string('recipient_type', 50)->default('record_partner');
                $table->string('fixed_recipient_email', 150)->nullable();
                $table->json('conditions')->nullable();
                $table->json('actions')->nullable();
                $table->char('created_by', 26)->nullable();
                $table->timestamps();

                $table->foreign('sender_profile_id')
                    ->references('id')->on('email_sender_profiles')
                    ->nullOnDelete();

                $table->foreign('created_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        // ── 2. email_automation_logs ───────────────────────────────────────
        if (! Schema::hasTable('email_automation_logs')) {
            Schema::create('email_automation_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('rule_id');
                $table->string('trigger', 50);
                $table->string('record_type', 100)->index();
                $table->string('record_id', 36)->index();
                $table->string('execution_mode', 30);
                $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected, executed, failed, skipped
                $table->string('idempotency_key', 150)->unique();
                $table->char('email_message_id', 26)->nullable();
                $table->unsignedBigInteger('approval_request_id')->nullable();
                $table->unsignedBigInteger('follow_up_task_id')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('rule_id')
                    ->references('id')->on('email_automation_rules')
                    ->cascadeOnDelete();

                $table->foreign('email_message_id')
                    ->references('id')->on('email_messages')
                    ->nullOnDelete();

                $table->index(['record_type', 'record_id']);
            });
        }

        // ── 3. email_approval_requests ─────────────────────────────────────
        if (! Schema::hasTable('email_approval_requests')) {
            Schema::create('email_approval_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('automation_log_id')->nullable();
                $table->char('email_message_id', 26)->nullable();
                $table->string('record_type', 100);
                $table->string('record_id', 36);
                $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected
                $table->char('requested_by', 26)->nullable();
                $table->char('approved_by', 26)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('automation_log_id')
                    ->references('id')->on('email_automation_logs')
                    ->cascadeOnDelete();

                $table->foreign('email_message_id')
                    ->references('id')->on('email_messages')
                    ->cascadeOnDelete();

                $table->foreign('requested_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->foreign('approved_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        // ── 4. email_follow_up_tasks ───────────────────────────────────────
        if (! Schema::hasTable('email_follow_up_tasks')) {
            Schema::create('email_follow_up_tasks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('automation_log_id')->nullable();
                $table->string('record_type', 100);
                $table->string('record_id', 36);
                $table->char('assigned_to', 26)->nullable();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status', 30)->default('open')->index(); // open, in_progress, completed, cancelled
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('automation_log_id')
                    ->references('id')->on('email_automation_logs')
                    ->nullOnDelete();

                $table->foreign('assigned_to')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        // ── 5. email_internal_notifications ────────────────────────────────
        if (! Schema::hasTable('email_internal_notifications')) {
            Schema::create('email_internal_notifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('user_id', 26);
                $table->string('title', 255);
                $table->text('message');
                $table->string('type', 50)->default('info');
                $table->string('record_type', 100)->nullable();
                $table->string('record_id', 36)->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_internal_notifications');
        Schema::dropIfExists('email_follow_up_tasks');
        Schema::dropIfExists('email_approval_requests');
        Schema::dropIfExists('email_automation_logs');
        Schema::dropIfExists('email_automation_rules');
    }
};
