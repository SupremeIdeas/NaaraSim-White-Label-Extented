<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed payout_account_fingerprints from the blind index already on existing
 * accounts (written by 2026_10_03_100400), so destination-sharing detection covers
 * accounts that were saved before the Guardian existed. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payout_accounts')->whereNotNull('lookup_hash')->orderBy('id')->each(function ($row) {
            DB::table('payout_account_fingerprints')->insertOrIgnore([
                'provider' => $row->provider,
                'fingerprint' => $row->lookup_hash,
                'user_id' => $row->user_id,
                'payout_account_id' => $row->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Derived data only.
    }
};
