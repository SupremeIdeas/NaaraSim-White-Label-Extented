<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutCorridor;
use App\Models\PayoutRequest;
use App\Models\Setting;

/**
 * Honest rail copy (Rail Guide §4): ETA text is admin-editable, never a promise we cannot
 * keep, and shows "Typical: X" only once there are >= 20 real settled payouts. Fees are the
 * PLATFORM fee only — provider cost is never user-facing (money rule 2).
 */
class RailCopy
{
    public const MIN_SAMPLES = 20;

    /** Current guide version; bumping it (admin) re-requires the global acknowledgement. */
    public static function guideVersion(): int
    {
        return max(1, (int) Setting::getValue('payouts.guide.version', 1));
    }

    public static function globalDays(): int
    {
        return max(1, (int) Setting::getValue('payouts.eta.global_days', 14));
    }

    /** @return array{text: string, typical_hours: ?int} */
    public function eta(string $rail, ?string $country = null): array
    {
        $override = $country ? app(PayoutRailRegistry::class)->note($country, $rail) : null;
        $custom = trim((string) Setting::getValue("payouts.eta.{$rail}", ''));

        $text = match (true) {
            $override?->eta_max_hours !== null => self::hours($override->eta_min_hours, $override->eta_max_hours),
            $custom !== '' => $custom,
            $rail === 'global' => (string) __('payout_guide.eta_global', ['days' => self::globalDays()]),
            $rail === 'stripe_connect' => (string) __('payout_guide.eta_stripe_connect'),
            default => (string) __('payout_guide.eta_local'),
        };

        return ['text' => $text, 'typical_hours' => $this->typicalHours($rail)];
    }

    /** Median created→settled hours across the rail's providers over 90 days, once enough samples exist. */
    private function typicalHours(string $rail): ?int
    {
        $secs = PayoutRequest::query()->whereIn('provider', PayoutRailRegistry::providersFor($rail))->where('status', PayoutRequest::PAID)
            ->whereNotNull('settled_at')->where('created_at', '>=', now()->subDays(90))->get(['created_at', 'settled_at'])
            ->map(fn ($r) => abs($r->settled_at->diffInSeconds($r->created_at)))->sort()->values();
        if ($secs->count() < self::MIN_SAMPLES) {
            return null;
        }

        return max(1, (int) round($secs[intdiv($secs->count(), 2)] / 3600));
    }

    /** The platform fee text for the first enabled corridor of this rail in the country. */
    public function fee(string $rail, string $country): string
    {
        $c = PayoutCorridor::query()->enabled()->where('country', strtoupper($country))->whereIn('provider', PayoutRailRegistry::providersFor($rail))->orderBy('priority')->first();
        if ($c === null || ((int) $c->platform_fee_bps === 0 && (float) $c->platform_fee_flat_usd == 0.0)) {
            return (string) __('payout_guide.fee_none');
        }
        $parts = array_filter([
            $c->platform_fee_bps > 0 ? rtrim(rtrim(number_format($c->platform_fee_bps / 100, 2), '0'), '.').'%' : null,
            (float) $c->platform_fee_flat_usd > 0 ? '$'.number_format((float) $c->platform_fee_flat_usd, 2) : null,
        ]);

        return (string) __('payout_guide.fee_some', ['fee' => implode(' + ', $parts)]);
    }

    private static function hours(?int $min, int $max): string
    {
        $fmt = fn (int $h) => $h >= 48 ? intdiv($h, 24).' d' : $h.' h';

        return $min !== null && $min !== $max ? $fmt($min).' – '.$fmt($max) : '≤ '.$fmt($max);
    }
}
