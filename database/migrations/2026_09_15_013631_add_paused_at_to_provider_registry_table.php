<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request (2026-09-15) — a durable admin "pause/sleep" for a single
 * provider, distinct from `enabled` (which ProviderHealth::upsertRegistry()
 * already overwrites every 15 minutes to mirror ProviderStatus::isActive(),
 * so it can't double as a manual override) and distinct from the circuit
 * breaker (which self-heals after a short cooldown and can even be bypassed
 * during a total-outage last resort). A non-null paused_at is a hard,
 * admin-only exclusion from routing until an admin explicitly resumes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_registry', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('provider_registry', function (Blueprint $table) {
            $table->dropColumn('paused_at');
        });
    }
};
