<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payouts are now AUTOMATIC by default (the Guardian approves low-risk requests on the tested rails). An install
 * that was already using payouts under the old manual/advisory defaults must not change behaviour silently on
 * update, so its previous effective values are written down explicitly. A fresh install (payouts never used) gets
 * the new defaults. Idempotent: never overwrites a value the admin already chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('payout_requests')) {
            return;
        }
        $used = Setting::getValue('payouts.enabled', false) || DB::table('payout_requests')->exists();
        if (! $used) {
            return;
        }

        $pins = [
            'payouts.mode' => 'manual',
            'payouts.auto_approval.enabled' => false,
            'payouts.auto_approval.shadow' => true,
        ];
        foreach (['paystack', 'flutterwave', 'paypal', 'stripe', 'cryptomus'] as $p) {
            $pins["payouts.provider.{$p}.auto_approve"] = false;
        }
        foreach ($pins as $key => $value) {
            if (! Setting::where('key', $key)->exists()) {
                Setting::setValue($key, $value, 'payouts');
            }
        }
    }

    public function down(): void
    {
        // Pinned values are the admin's to change; nothing to undo.
    }
};
