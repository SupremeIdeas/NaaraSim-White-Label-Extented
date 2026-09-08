<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Gift money-safety: a product is `priceable` only when its face value can
 * be converted to a real cost in our (USD) account currency — same currency, a
 * fixed recipient→sender map, or a sender range. Non-priceable cards are withheld
 * from the storefront so a currency mismatch can never sell a card below cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_products', function (Blueprint $table) {
            $table->boolean('priceable')->default(true)->after('provider_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_products', function (Blueprint $table) {
            $table->dropColumn('priceable');
        });
    }
};
