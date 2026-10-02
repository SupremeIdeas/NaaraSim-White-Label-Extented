<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Payout Layer — Phase 1: the corridor table. One row = "we can pay
 * <currency> into <country> through <provider> using <method>". The router reads
 * this to decide which rails a payee may use; rows ship DISABLED and an admin
 * enables each corridor only after it is verified against the provider sandbox.
 * `est_provider_cost_bps` is ADMIN-ONLY (money rule 2: cost is never user-facing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_corridors', function (Blueprint $table) {
            $table->id();
            $table->char('country', 2);
            $table->char('currency', 3);
            $table->string('provider');
            $table->string('method')->default('bank'); // bank|mobile_money|wallet|paypal|crypto|stripe_connect
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('priority')->default(100); // lower = tried first
            $table->decimal('min_usd', 12, 2)->nullable();
            $table->decimal('max_usd', 12, 2)->nullable();
            $table->unsignedSmallInteger('platform_fee_bps')->default(0);
            $table->decimal('platform_fee_flat_usd', 8, 2)->default(0);
            $table->unsignedSmallInteger('est_provider_cost_bps')->nullable(); // ADMIN ONLY
            $table->string('eta_text')->nullable();
            $table->string('requires_kyc_level')->nullable();
            $table->json('destination_types')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['country', 'currency', 'provider', 'method'], 'payout_corridors_unique');
            $table->index(['country', 'enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_corridors');
    }
};
