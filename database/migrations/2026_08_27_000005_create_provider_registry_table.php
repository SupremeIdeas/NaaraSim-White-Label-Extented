<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA-BUILD-14 — Provider Registry (NCI Layer 1). ONE shared operational
 * snapshot for every provider across all three stacks (esim / sms / permanent),
 * not three separate registries. Live-truth fields are written by ProviderHealth
 * (Layer 1); the reliability + circuit-breaker columns are added here but written
 * later by the Routing Engine (Layer 2, BUILD-15); NCI columns come in BUILD-16.
 * This migration only gives every downstream layer a home so they need no further
 * schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_registry', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key')->unique();      // esimgo, twilio, …
            $table->string('stack');                        // esim | sms | permanent
            $table->json('product_families')->nullable();   // Naara Data/Verify/Rent/Line…

            // Admin-facing onboarding metadata (drives Ops Center sort in BUILD-17).
            $table->string('onboarding_tier')->default('self_service'); // self_service|individual_kyc|small_business|enterprise

            // Practical admin links (restored per §2) — where to grab/rotate keys.
            $table->string('dashboard_login_url')->nullable();
            $table->string('docs_url')->nullable();
            $table->string('contact_email')->nullable();

            // Live-truth snapshot (written by ProviderHealth — same semantics as the
            // existing cache, just persisted, plus latency which was never recorded).
            $table->string('status')->default('coming_soon'); // ok|low|down|configured|coming_soon
            $table->decimal('balance', 14, 4)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();

            // Rolling reliability — computed from real purchase attempts (BUILD-15).
            // Left at defaults / null here.
            $table->unsignedInteger('success_count_24h')->default(0);
            $table->unsignedInteger('failure_count_24h')->default(0);
            $table->decimal('success_rate_24h', 5, 4)->nullable();

            // Circuit-breaker state — COLUMN ONLY in this batch. Transition logic is
            // the Routing Engine's job (BUILD-15).
            $table->string('circuit_breaker_state')->default('closed'); // closed|open|half_open

            // Mirrors the existing per-provider "active" toggle (ProviderStatus is
            // authoritative); persisted here as a snapshot so the registry is a
            // single readable row per provider.
            $table->boolean('enabled')->default(true);

            $table->timestamps();

            $table->index(['stack', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_registry');
    }
};
