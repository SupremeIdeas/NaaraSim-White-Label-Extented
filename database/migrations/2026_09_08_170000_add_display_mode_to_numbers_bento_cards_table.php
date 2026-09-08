<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request 2026-09-08: admin should be able to choose, per bento card,
 * whether tapping it opens a modal (the current behaviour for
 * verify/rent/line) or a dedicated inner page (already how
 * call_forwarding/internet_calls/contact_management work) — mirroring the
 * eSIM catalogue's own dedicated-page purchase flow. Nullable: null means
 * "use the card's own default" (App\Support\NumbersBento::defaults()'s
 * hardcoded link type), so every existing install is completely unchanged
 * until an admin actually picks a mode for a card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbers_bento_cards', function (Blueprint $table) {
            $table->string('display_mode')->nullable()->after('is_active'); // 'modal' | 'page' | null (default)
        });
    }

    public function down(): void
    {
        Schema::table('numbers_bento_cards', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    }
};
