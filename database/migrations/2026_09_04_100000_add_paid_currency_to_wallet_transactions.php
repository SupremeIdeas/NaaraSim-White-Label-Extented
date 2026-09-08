<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified USD Wallet blueprint, Part B §3.2 — every top-up now settles in USD
 * regardless of what the user actually paid in. These two nullable columns
 * preserve that original payment for transparency ("Topped up $42.10 (₦65,000
 * via Paystack)") without making the original currency spendable separately —
 * the ledger itself only ever moves in USD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->decimal('paid_amount', 18, 4)->nullable()->after('currency');
            $table->string('paid_currency', 4)->nullable()->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'paid_currency']);
        });
    }
};
