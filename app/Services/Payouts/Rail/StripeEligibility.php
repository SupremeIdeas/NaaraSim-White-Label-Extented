<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\User;

/**
 * Is this payee eligible for Stripe Connect payouts? The corridor table decides real
 * availability (Stripe's own rules differ by version — see the main blueprint 3b), plus
 * any earlier declined onboarding for this user.
 */
class StripeEligibility
{
    public const COUNTRY_NOT_SUPPORTED = 'country_not_supported';

    public const ONBOARDING_DECLINED = 'onboarding_declined';

    public const RECIPIENT_AGREEMENT = 'recipient_agreement_unsupported';

    public const USER_CHOICE = 'user_choice';

    /** @return array{eligible: bool, reason: ?string} */
    public function check(string $country, ?User $user = null): array
    {
        $country = strtoupper($country);

        $declined = $user !== null && PayoutAccount::where('user_id', $user->id)->where('provider', 'stripe')
            ->whereIn('provider_status', ['declined', 'rejected'])->exists();
        if ($declined) {
            return ['eligible' => false, 'reason' => self::ONBOARDING_DECLINED];
        }

        if (! StripeRegions::allows($country)) {
            return ['eligible' => false, 'reason' => self::COUNTRY_NOT_SUPPORTED]; // Stripe's own reach, per its docs
        }

        $enabled = PayoutCorridor::query()->enabled()->where('provider', 'stripe')->where('country', $country)->exists();

        return $enabled
            ? ['eligible' => true, 'reason' => null]
            : ['eligible' => false, 'reason' => self::COUNTRY_NOT_SUPPORTED];
    }
}
