<?php

namespace App\Support\Reviews;

use App\Models\ReviewMoment;
use App\Models\Setting;
use App\Models\User;
use App\Support\Trustpilot\TrustpilotSettings;

/**
 * Review moments: ask a person how it went, ONCE, at a calm moment, after something they bought or used actually worked (an eSIM delivered, a verification code
 * received, a gift card delivered...). The sheet shows when the person has been idle for a while or comes back to the app, never mid-task.
 *
 * Trustpilot rules this honours:
 *  - WHO is asked depends only on an objective completed experience (the product was delivered), never on how they felt. Nobody is filtered out for being unhappy.
 *  - Every answer, whatever the stars, gets the same invitation to Trustpilot. A 1 to 3 star answer ALSO gets a support link; it never replaces the invitation.
 *  - Nothing is offered or paid for a review. We never see or touch what is written on Trustpilot.
 *  - The stars are kept privately as a quality signal; asking is rate-limited per person.
 * OFF until an admin switches it on AND a Trustpilot profile address is set.
 */
final class ReviewMoments
{
    /** key => label, sheet title and line, minutes to wait after the success, first-only. */
    public const BASE = [
        'esim_first_purchase' => ['label' => 'First eSIM', 'title' => 'How was getting your eSIM?', 'sub' => 'Your eSIM is ready. Tell us how buying and setting it up went.', 'delay' => 30, 'first_only' => true],
        'sms_verification' => ['label' => 'Verification number', 'title' => 'How was your verification number?', 'sub' => 'Your code arrived. Tell us how it went.', 'delay' => 5, 'first_only' => true],
        'virtual_number' => ['label' => 'Phone number', 'title' => 'How was getting your phone number?', 'sub' => 'Your number is live. Tell us how it went.', 'delay' => 30, 'first_only' => true],
        'giftcard_purchase' => ['label' => 'Gift card', 'title' => 'How was your gift card purchase?', 'sub' => 'Your gift card was delivered. Tell us how it went.', 'delay' => 15, 'first_only' => true],
    ];

    /** @return array<string, array{label:string,title:string,sub:string,delay:int,first_only:bool}> */
    public static function catalog(): array
    {
        $all = self::BASE;
        return $all;
    }

    // ---- settings ------------------------------------------------------------------------------------------------------------------------

    private static function get(string $k, mixed $d): mixed
    {
        try {
            return Setting::getValue('reviews.moments.'.$k, $d);
        } catch (\Throwable) {
            return $d;
        }
    }

    public static function put(string $k, mixed $v): void
    {
        Setting::setValue('reviews.moments.'.$k, $v, 'reviews');
    }

    public static function reviewUrl(): ?string
    {
        return TrustpilotSettings::reviewFormUrl();
    }

    public static function switchedOn(): bool
    {
        return (bool) self::get('enabled', false);
    }

    /** Live only with the admin switch AND a Trustpilot profile to send people to. */
    public static function enabled(): bool
    {
        return self::switchedOn() && self::reviewUrl() !== null;
    }

    public static function idleMinutes(): int
    {
        return max(1, min(240, (int) self::get('idle_minutes', 15)));
    }

    public static function cooldownDays(): int
    {
        return max(0, min(365, (int) self::get('cooldown_days', 14)));
    }

    public static function windowDays(): int
    {
        return max(1, min(90, (int) self::get('window_days', 14)));
    }

    public static function momentOn(string $key): bool
    {
        return (bool) self::get($key.'.on', true);
    }

    public static function delayMinutes(string $key): int
    {
        return max(0, min(10080, (int) self::get($key.'.delay', self::catalog()[$key]['delay'] ?? 0)));
    }

    // ---- recording -----------------------------------------------------------------------------------------------------------------------

    /** A completed experience. Idempotent per (person, moment, ref); a "first only" moment is recorded once per person. Never throws into the caller (a money path). */
    public static function qualify(?int $userId, string $moment, int|string $ref): void
    {
        try {
            $cat = self::catalog()[$moment] ?? null;
            if ($cat === null || ! $userId) {
                return;
            }
            if ($cat['first_only'] && ReviewMoment::query()->where('user_id', $userId)->where('moment', $moment)->exists()) {
                return;
            }
            ReviewMoment::query()->firstOrCreate(['user_id' => $userId, 'moment' => $moment, 'ref' => (string) $ref], ['qualified_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ---- asking --------------------------------------------------------------------------------------------------------------------------

    /** Is the experience this row is about still a success? (A refunded or failed order is not asked about.) */
    private static function stillValid(ReviewMoment $m): bool
    {
        return match ($m->moment) {
            'esim_first_purchase' => ! in_array(\App\Models\EsimOrder::query()->whereKey($m->ref)->value('status'), [null, 'failed', 'refunded', 'cancelled'], true),
            'sms_verification' => \App\Models\SmsOrder::query()->whereKey($m->ref)->value('status') === 'completed',
            'virtual_number' => \App\Models\VirtualNumber::query()->whereKey($m->ref)->value('status') === 'active',
            'giftcard_purchase' => \App\Models\GiftCardOrder::query()->whereKey($m->ref)->value('status') === 'delivered',
            default => false,
        };
    }

    /** The one moment to ask this person about right now, or null. Marks it as shown, so each is asked once. */
    public static function next(User $user): ?array
    {
        if (! self::enabled()) {
            return null;
        }
        $since = now()->subDays(self::cooldownDays());
        if (self::cooldownDays() > 0) {
            if (ReviewMoment::query()->where('user_id', $user->id)->where('shown_at', '>=', $since)->exists()) {
                return null;
            }
        }
        $cat = self::catalog();
        $rows = ReviewMoment::query()->where('user_id', $user->id)->whereNull('shown_at')->where('qualified_at', '>=', now()->subDays(self::windowDays()))->orderBy('qualified_at')->get();
        foreach ($rows as $m) {
            if (! isset($cat[$m->moment]) || ! self::momentOn($m->moment) || $m->qualified_at->gt(now()->subMinutes(self::delayMinutes($m->moment)))) {
                continue;
            }
            if (! self::stillValid($m)) {
                continue;
            }
            $m->forceFill(['shown_at' => now()])->save();

            return ['id' => $m->id, 'moment' => $m->moment, 'title' => $cat[$m->moment]['title'], 'sub' => $cat[$m->moment]['sub']];
        }

        return null;
    }

    /** @return array{review_url:string, support_url:?string} */
    public static function rate(ReviewMoment $m, int $stars): array
    {
        $stars = max(1, min(5, $stars));
        $m->forceFill(['stars' => $stars, 'outcome' => 'rated'])->save();

        return ['review_url' => (string) self::reviewUrl(), 'support_url' => $stars <= 3 && \Illuminate\Support\Facades\Route::has('support') ? route('support') : null];
    }
}
