<?php

namespace App\Services\Merchants;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\MerchantSettings;

/**
 * Merchant V2 upgrade (client-management tier). Two paths, both money-safe:
 *   • self-pay: the one-time upgrade price is charged from the merchant owner's
 *     wallet (same pattern as the enrollment fee — a service fee, atomic +
 *     idempotent, never through PricingEngine). Users fund the wallet through
 *     the platform's payment gateways, so this reuses that infrastructure.
 *   • admin-grant: an admin upgrades a merchant for free.
 * V2 is additive — the merchant keeps their normal reseller earnings unchanged.
 */
class MerchantUpgradeService
{
    public function __construct(private WalletService $wallet)
    {
    }

    /** Self-upgrade: charge the upgrade price from the wallet, then flip the tier. */
    public function selfUpgrade(Merchant $merchant): Merchant
    {
        if ($merchant->isV2()) {
            return $merchant; // already V2 — idempotent
        }
        if (! $merchant->isActive()) {
            throw new MerchantException('Your merchant account must be active to upgrade.');
        }

        $price = MerchantSettings::upgradePriceUsd();
        try {
            $this->wallet->debit($merchant->owner, $price, 'USD', [
                'reference' => 'merchant_v2_upgrade:'.$merchant->id,
                'description' => 'Merchant V2 upgrade',
            ]);
        } catch (InsufficientBalanceException $e) {
            throw new MerchantException('Top up your wallet with at least $'.number_format($price, 2).' to upgrade.');
        }

        return $this->markUpgraded($merchant, paid: $price);
    }

    /** Admin grants the V2 tier (no charge). */
    public function grant(Merchant $merchant, User $admin): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);

        return $this->markUpgraded($merchant, grantedBy: $admin->id);
    }

    /** Admin returns a merchant to the standard tier. */
    public function downgrade(Merchant $merchant, User $admin): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);
        $merchant->forceFill(['tier' => Merchant::TIER_STANDARD, 'upgraded_at' => null])->save();
        Auditor::log('merchant.v2_downgraded', 'Merchant', $merchant->id, ['by' => $admin->id]);

        return $merchant;
    }

    private function markUpgraded(Merchant $merchant, ?float $paid = null, ?int $grantedBy = null): Merchant
    {
        $merchant->forceFill(['tier' => Merchant::TIER_V2, 'upgraded_at' => now()])->save();
        Auditor::log('merchant.v2_upgraded', 'Merchant', $merchant->id, ['paid' => $paid, 'granted_by' => $grantedBy]);

        return $merchant;
    }
}
