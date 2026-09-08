<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe Connect onboarding status (ROADMAP §Layer 0.2 — Stripe payout rail).
 * Unlike a bank/PayPal account, which is either resolvable or not, a Stripe
 * Express account goes through a multi-step hosted onboarding flow before it
 * can receive transfers — these columns track where it is in that flow.
 * `is_verified` (existing column) is kept in sync with `payouts_enabled`, so
 * every existing money-path check that already gates on `is_verified`
 * (WithdrawalService, PayoutService::createRequest) automatically refuses an
 * account that hasn't finished onboarding, with no changes needed there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->boolean('details_submitted')->default(false)->after('is_verified');
            $table->boolean('charges_enabled')->default(false)->after('details_submitted');
            $table->boolean('payouts_enabled')->default(false)->after('charges_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropColumn(['details_submitted', 'charges_enabled', 'payouts_enabled']);
        });
    }
};
