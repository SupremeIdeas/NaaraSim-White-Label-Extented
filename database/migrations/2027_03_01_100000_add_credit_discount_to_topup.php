<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Credit discount on data top-ups. price_usd stays "what the customer pays from the wallet" (after the discount), so debits, refunds, caps and the profit
 * report need no change. These columns record the list price and the discount for transparency and for restoring credits on a refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['topup_quotes', 'topup_orders'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                $t->decimal('list_price_usd', 12, 4)->nullable()->after('price_usd');
                $t->decimal('credit_usd', 8, 2)->default(0)->after('list_price_usd');
                $t->decimal('credits', 12, 2)->default(0)->after('credit_usd');
            });
        }
    }

    public function down(): void
    {
        foreach (['topup_quotes', 'topup_orders'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) {
                $t->dropColumn(['list_price_usd', 'credit_usd', 'credits']);
            });
        }
    }
};
