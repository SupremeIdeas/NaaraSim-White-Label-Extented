<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The one card per brand the unfiltered store leads with (the US card, else the lowest id); country variants follow it.
        Schema::table('gift_card_products', function (Blueprint $table) {
            $table->boolean('is_lead')->default(false)->after('is_primary')->index();
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_products', function (Blueprint $table) {
            $table->dropColumn('is_lead');
        });
    }
};
