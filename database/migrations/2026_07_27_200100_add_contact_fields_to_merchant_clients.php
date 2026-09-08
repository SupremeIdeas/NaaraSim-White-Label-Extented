<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer client records for Merchant V2 premium client control — a WhatsApp
 * number (for one-tap renewal reminders + invoices) and an email, plus a
 * device OS hint for the compatibility check. Search covers all of these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_clients', function (Blueprint $table) {
            $table->string('whatsapp')->nullable()->after('contact');
            $table->string('email')->nullable()->after('whatsapp');
            $table->string('device_os')->nullable()->after('device'); // ios | android | other
        });
    }

    public function down(): void
    {
        Schema::table('merchant_clients', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'email', 'device_os']);
        });
    }
};
