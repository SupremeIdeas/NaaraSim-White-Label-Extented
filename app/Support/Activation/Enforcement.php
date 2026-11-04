<?php

namespace App\Support\Activation;

use App\Models\User;

/**
 * Staged enforcement for an install that is not (or no longer) properly licensed. It warns first and restricts later, and it never touches the public site:
 *
 *   ok         activated and in good standing: nothing shown
 *   grace      not activated yet, inside the 14-day set-up window: a friendly countdown banner for admins
 *   expired    not activated and the window is over: a firm banner that cannot be dismissed, and updates are withheld
 *   suspended  the platform suspended this licence: firm banner, updates withheld until it is restored
 *   revoked    the licence was revoked: firm banner, updates withheld
 *   unverified the platform answered with something this install cannot trust (wrong address, a copy pretending to be the platform, or a build without the public key): firm banner, updates withheld
 *   lapsed     activated, but no signed confirmation for 30 days (the last one expired): firm banner; the locks of a Normal fork are already closed because nothing is verified
 *   stale      activated, last confirmation more than 7 days ago: a warning to look at the cron or the platform address
 *   unreachable the platform could not be reached for a while: a quiet note only, because an outage on the platform's side must never punish a site
 *
 * The clock starts when the install first came to life (its earliest user, or the first time this ran), so waiting does not reset it.
 */
final class Enforcement
{
    public const GRACE_DAYS = 14;

    public static function firstSeen(): \Carbon\CarbonInterface
    {
        $stored = LicenseState::get('first_seen_at');
        $at = null;
        try {
            $u = User::query()->min('created_at');
            $at = $u ? \Illuminate\Support\Carbon::parse($u) : null;
        } catch (\Throwable) {
        }
        if (is_string($stored) && $stored !== '') {
            $s = \Illuminate\Support\Carbon::parse($stored);
            $at = $at === null || $s->lt($at) ? $s : $at;
        }
        if ($at === null) {
            $at = now();
        }
        if (! is_string($stored) || $stored === '') {
            try {
                LicenseState::put('first_seen_at', $at->toIso8601String());
            } catch (\Throwable) {
            }
        }

        return $at;
    }

    /** @return array{stage: string, days_left: ?int, title: string, message: string} */
    public static function stage(): array
    {
        $s = LicenseState::status();
        if ($s === 'none') {
            $left = (int) ceil(self::firstSeen()->copy()->addDays(self::GRACE_DAYS)->diffInHours(now(), false) / -24);
            if ($left >= 0) {
                return ['stage' => 'grace', 'days_left' => $left, 'title' => 'Activate your licence', 'message' => $left === 0
                    ? 'Today is the last day of your set-up window. Activate now with the licence key you received.'
                    : "You have {$left} ".($left === 1 ? 'day' : 'days').' left to activate with the licence key you received. Some features stay locked until you do.'];
            }

            return ['stage' => 'expired', 'days_left' => 0, 'title' => 'This site is not activated', 'message' => 'The set-up window has ended. Updates are withheld until this site is activated with a licence key. Your public site keeps running.'];
        }
        if ($s === 'revoked') {
            return ['stage' => 'revoked', 'days_left' => null, 'title' => 'This licence was revoked', 'message' => 'Updates are withheld. Contact the platform that licensed this install to get a new key.'];
        }
        if ($s === 'suspended') {
            return ['stage' => 'suspended', 'days_left' => null, 'title' => 'This licence is suspended', 'message' => 'Updates are withheld until the platform restores it. Your public site keeps running.'];
        }
        if ($s === 'unverified') {
            return ['stage' => 'unverified', 'days_left' => null, 'title' => 'The licensing platform could not be verified', 'message' => 'Its answer was not signed by the platform that licensed this install. Check the platform address on the activation screen. Updates are withheld until a signed answer arrives.'];
        }
        if (EntitlementVerifier::configured()) {
            $raw = (string) LicenseState::get('verified_at', '');
            $age = $raw !== '' ? (int) \Illuminate\Support\Carbon::parse($raw)->diffInDays(now(), true) : null;
            if ($s === 'active' && ($age === null || $age >= 30) && LicenseState::verifiedEntitlement() === null) {
                return ['stage' => 'lapsed', 'days_left' => null, 'title' => 'Your licence has not been confirmed for a month', 'message' => 'This install could not reach the licensing platform to renew its signed confirmation. Features that need a licence stay closed until it does. Check the cron job (schedule:run every minute) and the platform address.'];
            }
            if ($s === 'active' && $age !== null && $age >= 7) {
                return ['stage' => 'stale', 'days_left' => null, 'title' => 'Licence not confirmed for '.$age.' days', 'message' => 'The check-in with the licensing platform has not succeeded for a week. Make sure the cron job runs every minute and the platform address is right.'];
            }
        }
        if ($s === 'unreachable') {
            return ['stage' => 'unreachable', 'days_left' => null, 'title' => 'Could not reach the licensing platform', 'message' => 'We will keep trying. Nothing on your site changes.'];
        }

        return ['stage' => 'ok', 'days_left' => null, 'title' => '', 'message' => ''];
    }

    /** Platform-distributed updates and packages are withheld once the stage is firm. */
    public static function updatesAllowed(): bool
    {
        return ! in_array(self::stage()['stage'], ['expired', 'suspended', 'revoked', 'unverified', 'lapsed'], true);
    }
}
