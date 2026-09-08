<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks how many purchases a user has completed THROUGH the Wizard (roadmap §6).
 * The first few are free; after that a small, always-visible convenience fee
 * applies to wizard-completed purchases (the dashboard path stays free). The
 * counter is per user so the admin can reset/window it later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('wizard_uses')->default(0)->after('kyc_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wizard_uses');
        });
    }
};
