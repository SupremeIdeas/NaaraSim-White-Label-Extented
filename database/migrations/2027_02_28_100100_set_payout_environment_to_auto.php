<?php

use App\Models\Setting;
use App\Support\PayoutSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * Payouts must never be stuck in sandbox by an old `PAYOUT_ENV=sandbox` line (it shipped in .env.example). Sites that have never chosen get "Auto", which follows where
 * the app runs: a real https website is live. A choice an admin already saved is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        try {
            if (! Setting::query()->where('key', PayoutSettings::ENV_MODE)->exists()) {
                Setting::setValue(PayoutSettings::ENV_MODE, 'auto', 'payouts');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void {}
};
