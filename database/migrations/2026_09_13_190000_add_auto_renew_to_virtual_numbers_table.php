<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt 10 — consumer auto-renewal opt-out + advance-notice for permanent
 * numbers (Naara Line). Mirrors the exact shape already proven for merchant
 * client subscriptions (auto_renew boolean + a reset-per-cycle notice marker
 * — MerchantClientSubscription.due_alerted_at), rather than inventing a new
 * pattern. `renewal_notice_sent_at` is reset to null every time the number
 * actually renews (RenewVirtualNumbersCommand), since — unlike a one-shot
 * subscription expiry — a Naara Line renews monthly indefinitely while
 * auto_renew stays true, so the marker must clear each cycle or the customer
 * would only ever be notified once in the number's whole lifetime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true)->after('status');
            $table->timestamp('renewal_notice_sent_at')->nullable()->after('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->dropColumn(['auto_renew', 'renewal_notice_sent_at']);
        });
    }
};
