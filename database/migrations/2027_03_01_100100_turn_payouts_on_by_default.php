<?php

use App\Models\Setting;
use App\Support\PayoutSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * Owner 2026-11-15: nothing payout-related ships switched off. Grey is live (KYB verified) and runs beside Paystack and NOWPayments.
 * Existing installs get the same switches on once; a value an admin changes afterwards is theirs and is never touched again (this runs once).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        try {
            foreach ([PayoutSettings::FLAG, PayoutSettings::MANUAL_EXTERNAL, PayoutSettings::AUTO_APPROVAL, PayoutSettings::PEER_ENABLED,
                PayoutSettings::PROVIDER_AUTO_PREFIX.'grey.auto_approve', PayoutSettings::PROVIDER_AUTO_PREFIX.'nowpayments.auto_approve'] as $key) {
                Setting::setValue($key, true, 'payouts');
            }
            Setting::setValue(PayoutSettings::AUTO_SHADOW, false, 'payouts');
            $off = array_values(array_diff(PayoutSettings::disabledExtensions(), ['grey', 'nowpayments']));
            Setting::setValue(PayoutSettings::DISABLED_EXTENSIONS, implode(',', $off), 'payouts');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void {}
};
