<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant V2 multi-month auto-renew: how many renewal cycles a merchant has
 * PRE-FUNDED (earmarked) for a client's eSIM. Each due renewal consumes one and
 * carries the rest onto the fresh subscription, so a merchant can lock a client's
 * line for 2, 6, 12+ months up front and it keeps renewing hands-free.
 *
 * `renew_indefinitely` is the "keep it for life" mode: instead of a finite
 * pre-funded block, we keep exactly ONE cycle reserved and top it back up after
 * every renewal (rolling earmark), so the line renews forever as long as the
 * merchant keeps funds — you can't literally pre-fund infinite months.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_client_subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('reserved_cycles')->default(0)->after('reserve_reference');
            $table->boolean('renew_indefinitely')->default(false)->after('reserved_cycles');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_client_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['reserved_cycles', 'renew_indefinitely']);
        });
    }
};
