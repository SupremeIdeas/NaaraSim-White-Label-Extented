<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier 5 #11 Phase B. Marks when the live in-session hero-toast invite
     * has already been shown, so the header's mobile and desktop
     * NotificationCenter instances (each polling independently) never
     * double-toast the same still-pending invite.
     */
    public function up(): void
    {
        Schema::table('wallet_group_members', function (Blueprint $table) {
            $table->timestamp('toast_shown_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_group_members', function (Blueprint $table) {
            $table->dropColumn('toast_shown_at');
        });
    }
};
