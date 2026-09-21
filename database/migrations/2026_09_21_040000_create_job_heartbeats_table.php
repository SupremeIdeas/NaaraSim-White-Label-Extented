<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 4 #10 Phase B1 — a real per-run log for every scheduled command:
 * when it started/finished, how long it took, and its outcome
 * (success/failed/skipped) with a short human-readable detail. Replaces
 * SchedulerHealth's bare last-run timestamp as the single source of truth
 * both the System Health hero and its overdue math read from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('outcome'); // success | failed | skipped
            $table->text('detail')->nullable();
            $table->timestamps();

            $table->index(['job_name', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_heartbeats');
    }
};
