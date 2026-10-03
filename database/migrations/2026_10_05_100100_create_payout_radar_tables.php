<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funding Radar (Addendum A) R3: append-only time series. The radar READS the money
 * tables and WRITES only here (and to cache keys) — it never touches wallets,
 * earnings or payout_requests. Historical rows are never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_exposure_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamp('captured_at');
            $table->string('provider');
            $table->char('country', 2)->nullable();   // null = all countries
            $table->char('currency', 3)->nullable();  // null = all currencies
            $table->unsignedInteger('users_selected')->default(0);
            $table->unsignedInteger('users_onboarding')->default(0);
            $table->unsignedInteger('users_active')->default(0);
            $table->decimal('balance_credits_usd', 18, 4)->default(0);
            $table->decimal('balance_referral_usd', 18, 4)->default(0);
            $table->decimal('balance_merchant_usd', 18, 4)->default(0);
            $table->decimal('balance_partner_usd', 18, 4)->default(0);
            $table->decimal('balance_staff_usd', 18, 4)->default(0);
            $table->decimal('max_exposure_usd', 18, 4)->default(0);
            $table->decimal('committed_unsent_usd', 18, 4)->default(0);
            $table->decimal('in_flight_usd', 18, 4)->default(0);
            $table->decimal('due_next_sweep_usd', 18, 4)->default(0);
            $table->decimal('forecast_7d_p50_usd', 18, 4)->default(0);
            $table->decimal('forecast_7d_p90_usd', 18, 4)->default(0);
            $table->decimal('float_available_usd', 18, 4)->nullable();
            $table->decimal('recommended_topup_p50_usd', 18, 4)->default(0);
            $table->decimal('recommended_topup_p90_usd', 18, 4)->default(0);
            $table->boolean('low_confidence')->default(true);

            $table->index('captured_at');
            $table->index(['provider', 'captured_at']);
        });

        Schema::create('payout_withdrawal_stats_hourly', function (Blueprint $table) {
            $table->id();
            $table->timestamp('hour_start');
            $table->string('provider');
            $table->char('country', 2)->default('ZZ');
            $table->unsignedInteger('requests_count')->default(0);
            $table->decimal('requested_usd', 18, 4)->default(0);
            $table->unsignedInteger('paid_count')->default(0);
            $table->decimal('paid_usd', 18, 4)->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('failed_usd', 18, 4)->default(0);
            $table->unsignedInteger('avg_time_to_paid_sec')->nullable();
            $table->timestamps();

            $table->unique(['hour_start', 'provider', 'country'], 'payout_stats_hourly_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_withdrawal_stats_hourly');
        Schema::dropIfExists('payout_exposure_snapshots');
    }
};
