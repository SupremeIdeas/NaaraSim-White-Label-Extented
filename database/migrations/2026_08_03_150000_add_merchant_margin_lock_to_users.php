<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-4 §3.3 — referral-margin lock-in. When a user registers through a
 * merchant's invite link, the merchant's reseller margin AT THAT MOMENT is
 * snapshotted here and used for that user's pricing for the life of the account,
 * regardless of later margin changes (unless an admin deliberately overrides it).
 * Null = no lock (a normal user, or a legacy merchant-referred user before this
 * build — those fall back to the merchant's current margin, backward-compatible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('merchant_margin_pct', 6, 3)->nullable()->after('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('merchant_margin_pct');
        });
    }
};
