<?php

use App\Support\GiftCardCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gift_card_products', 'category_key')) {
            Schema::table('gift_card_products', function (Blueprint $table) {
                $table->string('category_key', 40)->nullable()->after('category')->index();
            });
        }

        // Backfill every synced card once; later syncs set it themselves.
        DB::table('gift_card_products')->select('id', 'category', 'brand_name')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('gift_card_products')->where('id', $r->id)->update(['category_key' => GiftCardCategories::resolve($r->category, $r->brand_name)]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('gift_card_products', 'category_key')) {
            Schema::table('gift_card_products', function (Blueprint $table) {
                $table->dropColumn('category_key');
            });
        }
    }
};
