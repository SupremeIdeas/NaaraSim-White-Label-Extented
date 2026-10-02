<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conservative corridor defaults (Global Payout Layer, Phase 1). Only Nigeria
 * (the one market the engine already pays today) ships ENABLED. Every other
 * market a resolver already supports is listed but DISABLED until an admin has
 * verified it on the provider sandbox. Nothing here adds a new provider.
 * Idempotent: re-running never overwrites an admin's changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // country, currency, provider, priority, enabled, eta
            ['NG', 'NGN', 'paystack', 10, true, 'Usually within minutes'],
            ['NG', 'NGN', 'flutterwave', 20, true, 'Usually within minutes'],
            ['GH', 'GHS', 'paystack', 10, false, null],
            ['GH', 'GHS', 'flutterwave', 20, false, null],
            ['KE', 'KES', 'paystack', 10, false, null],
            ['KE', 'KES', 'flutterwave', 20, false, null],
            ['ZA', 'ZAR', 'paystack', 10, false, null],
            ['ZA', 'ZAR', 'flutterwave', 20, false, null],
            ['EG', 'EGP', 'paystack', 10, false, null],
            ['EG', 'EGP', 'flutterwave', 20, false, null],
            ['CI', 'XOF', 'paystack', 10, false, null],
            ['CI', 'XOF', 'flutterwave', 20, false, null],
            ['UG', 'UGX', 'flutterwave', 20, false, null],
            ['TZ', 'TZS', 'flutterwave', 20, false, null],
            ['RW', 'RWF', 'flutterwave', 20, false, null],
            ['ZM', 'ZMW', 'flutterwave', 20, false, null],
            ['SN', 'XOF', 'flutterwave', 20, false, null],
            ['CM', 'XAF', 'flutterwave', 20, false, null],
        ];

        $now = now();
        foreach ($rows as [$country, $currency, $provider, $priority, $enabled, $eta]) {
            DB::table('payout_corridors')->insertOrIgnore([
                'country' => $country, 'currency' => $currency, 'provider' => $provider, 'method' => 'bank',
                'enabled' => $enabled, 'priority' => $priority, 'eta_text' => $eta,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Seed data only; the table itself is dropped by its own migration.
    }
};
