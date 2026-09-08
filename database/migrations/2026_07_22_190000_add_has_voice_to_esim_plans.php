<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full eSIM (calls + data) flag (esim_upgrade Part 2). Distinguishes the new
 * voice-enabled "Full eSIMs" line from data-only plans, so the storefront's
 * "eSIM Data" / "Full eSIMs" tabs filter on one column. Data-only plans default
 * to false; a voice-capable provider (e.g. Zendit) marks its bundles true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->boolean('has_voice')->default(false)->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->dropColumn('has_voice');
        });
    }
};
