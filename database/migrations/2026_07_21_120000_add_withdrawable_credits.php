<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROADMAP §Layer 1 — NaaraCredit → cash. Only credits earned from a person's
 * FIRST referral each are withdrawable; everything else (check-ins, ad rewards,
 * bonuses) stays spend-only. We track a `withdrawable_credits` subset of the
 * credit balance, flag the earning ledger rows, and record the exact credits
 * held against each credit-sourced payout so a failed transfer returns precisely
 * what was taken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->decimal('withdrawable_credits', 14, 2)->default(0)->after('naara_credits');
        });

        Schema::table('credit_ledger', function (Blueprint $table) {
            $table->boolean('withdrawable')->default(false)->after('source');
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            // Credits held for a credit-sourced withdrawal (returned on reversal).
            $table->decimal('credit_amount', 14, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('user_wallets', fn (Blueprint $table) => $table->dropColumn('withdrawable_credits'));
        Schema::table('credit_ledger', fn (Blueprint $table) => $table->dropColumn('withdrawable'));
        Schema::table('payout_requests', fn (Blueprint $table) => $table->dropColumn('credit_amount'));
    }
};
