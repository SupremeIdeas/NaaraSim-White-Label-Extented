<?php

namespace App\Support;

use App\Models\EsimPlan;
use App\Services\NCI\NciScorer;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use Illuminate\Support\Facades\Cache;

/**
 * The single source of country data behind the shared CountryPicker modal
 * (blueprint Section 31 — one country-picking UI app-wide). Each `source`
 * returns a normalised, name-sorted list of pickable countries with a live
 * count, so the picker itself stays a dumb, reusable view.
 *
 * `[ ['code' => 'NG', 'name' => 'Nigeria', 'count' => 12], ... ]`
 *
 * eSIM sources are implemented here; the Numbers build adds its own case
 * ('otp' / 'rental' / 'permanent') against the same contract, reusing the modal.
 */
class CountryPickerSources
{
    /**
     * @return array<int, array{code: string, name: string, count: int}>
     */
    public static function options(string $source, array $args = []): array
    {
        return match ($source) {
            'esim' => self::esim((bool) ($args['has_voice'] ?? false)),
            'numbers' => self::numbers(),
            default => [],
        };
    }

    /**
     * Every country a phone number can be bought in (Numbers V6). Slug-keyed
     * (the buy flow uses 5sim-style slugs), each with its dial code — the picker
     * shows flag + name + dial code in this mode.
     *
     * `success` (Prompt 10) is the best public success rate (0-1, or null)
     * among that country's OTP lane — the SAME lane SmsNumberRouter would
     * actually try, so the badge reflects real routing, not a guess. Null
     * means "not enough data yet," never "0%" — the view must treat it as
     * hidden, not a bad score.
     *
     * @return array<int, array{code: string, name: string, dial: ?string, success: ?float}>
     */
    private static function numbers(): array
    {
        return Cache::remember('country_picker:numbers', now()->addMinutes(15), function () {
            $router = app(SmsNumberRouter::class);
            $scorer = app(NciScorer::class);

            $rows = [];
            foreach (\App\Support\NumberCatalogue::countries() as $slug => $label) {
                $lane = $router->laneFor($slug, NumberRequest::TYPE_OTP);
                $rows[] = [
                    'code' => $slug,
                    'name' => $label,
                    'dial' => \App\Support\DialCodes::for($slug),
                    'success' => $scorer->bestPublicSuccessRate($lane),
                ];
            }

            usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return $rows;
        });
    }

    /**
     * Distinct countries covered by active eSIM plans on one line (data vs Full
     * eSIM), each with how many plans reach it. Cached briefly — the set only
     * changes on a catalogue sync.
     *
     * @return array<int, array{code: string, name: string, count: int}>
     */
    private static function esim(bool $hasVoice): array
    {
        $key = 'country_picker:esim:'.($hasVoice ? 'full' : 'data');

        return Cache::remember($key, now()->addMinutes(10), function () use ($hasVoice) {
            $tally = [];

            EsimPlan::query()
                ->where('is_active', true)
                ->where('has_voice', $hasVoice)
                ->pluck('countries')
                ->each(function ($countries) use (&$tally) {
                    foreach ((array) $countries as $c) {
                        $iso = CountryFlags::iso((string) $c);
                        if ($iso === null) {
                            continue;
                        }
                        $iso = strtoupper($iso);
                        $tally[$iso] = ($tally[$iso] ?? 0) + 1;
                    }
                });

            $rows = [];
            foreach ($tally as $iso => $count) {
                $rows[] = ['code' => $iso, 'name' => CountryNames::name($iso), 'count' => $count];
            }

            usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return $rows;
        });
    }

    /** Drop the cached country tallies (call after a catalogue sync). */
    public static function flush(): void
    {
        Cache::forget('country_picker:esim:data');
        Cache::forget('country_picker:esim:full');
        Cache::forget('country_picker:numbers');
    }
}
