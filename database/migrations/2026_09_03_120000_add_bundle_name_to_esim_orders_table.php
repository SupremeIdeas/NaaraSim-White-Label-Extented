<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The provider's own bundle/SKU identifier for the plan actually fulfilled
 * (Connectivity Analytics blueprint Part A). Needed because `EsimOrder.plan_id`
 * is NaaraSim's canonical plan and can diverge from what was actually ordered
 * on a ProviderRouter failover — `getUsage($iccid, $bundleName)` needs the real
 * one. Nullable: pre-existing orders and providers whose getUsage() ignores the
 * bundle name entirely (most of them — only eSIM Go's endpoint uses it) are
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esim_orders', function (Blueprint $table) {
            $table->string('bundle_name')->nullable()->after('iccid');
        });
    }

    public function down(): void
    {
        Schema::table('esim_orders', function (Blueprint $table) {
            $table->dropColumn('bundle_name');
        });
    }
};
