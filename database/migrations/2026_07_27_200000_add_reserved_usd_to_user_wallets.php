<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserved (earmarked) USD for Merchant V2 auto-renewal (client eSIM control).
 * When a merchant marks a client for auto-billing, the next renewal amount is
 * RESERVED here — it stays in the wallet but is subtracted from spendable, so it
 * "cannot be reused for any other transaction" until the due-date run settles or
 * releases it. Every USD debit now checks balance − reserved (WalletService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->decimal('reserved_usd', 18, 4)->default(0)->after('usd_balance');
        });
    }

    public function down(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->dropColumn('reserved_usd');
        });
    }
};
