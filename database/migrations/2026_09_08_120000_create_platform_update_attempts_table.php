<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Updater — Batch 2 §3. Every attempt to apply a `.naaraupdate`
 * package writes one row here: its outcome, the exact backup archive used for
 * rollback, how long the platform was down, and (on failure) why. This is both
 * the operational history an admin wants ("what was applied, when, how long was
 * each downtime window") and the audit trail a white-label brand owner will
 * reasonably want visibility into for their own instance in Batch 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_update_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('package_id');            // from manifest.json's package_id
            $table->string('from_version')->nullable();
            $table->string('to_version');
            // pending, applying, succeeded, rolled_back, failed_unrecoverable
            $table->string('status')->default('pending');
            $table->string('backup_archive_path')->nullable();
            $table->unsignedInteger('files_changed_count')->nullable();
            $table->unsignedInteger('migrations_run_count')->nullable();
            $table->unsignedInteger('downtime_seconds')->nullable();
            $table->json('health_check_result')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('maintenance_started_at')->nullable();
            $table->timestamp('maintenance_ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_update_attempts');
    }
};
