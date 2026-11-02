<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The master platform's Brand Hunt demo brands (and their handles and photos) were seeded into white-label installs by mistake: that showcase content belongs
 * to the master only. Remove exactly those four, and only when nobody owns them and nothing was ever billed to them. A brand a person created themselves (even with
 * the same name) is never touched. Safe to run twice.
 */
return new class extends Migration
{
    private const DEMO = ['Sahara Airways', 'Lagos Eats', 'Naija Fashion Co', 'Sahara Connect Shop'];

    public function up(): void
    {
        if (! Schema::hasTable('brand_partners')) {
            return;
        }
        $ids = DB::table('brand_partners')->whereIn('brand_name', self::DEMO)->whereNull('owner_user_id')->pluck('id')->all();
        if (Schema::hasTable('brand_subscriptions')) {
            $billed = DB::table('brand_subscriptions')->whereIn('brand_partner_id', $ids)->pluck('brand_partner_id')->all();
            $ids = array_values(array_diff($ids, $billed));
        }
        if ($ids === []) {
            return;
        }
        foreach (['brand_partner_handles', 'brand_partner_images', 'brand_partner_videos', 'brand_partner_followers', 'brand_priority_log'] as $child) {
            if (Schema::hasTable($child)) {
                DB::table($child)->whereIn('brand_partner_id', $ids)->delete();
            }
        }
        DB::table('brand_partners')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // The demo content is not restored.
    }
};
