<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Autopilot (BUILD-4 §7). Automated WhatsApp notifications require an
 * explicit user opt-in under Meta's policy, so we store the opt-in flag and an
 * optional dedicated WhatsApp number (falls back to `phone` when empty). Opt-in
 * defaults to FALSE — we never message a user who has not asked us to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->boolean('whatsapp_opt_in')->default(false)->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'whatsapp_opt_in']);
        });
    }
};
