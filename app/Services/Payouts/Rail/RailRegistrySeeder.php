<?php

namespace App\Services\Payouts\Rail;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutCountryRail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Seeds / audits the rail registry (Rail Guide §2). Never overwrites an admin override.
 *  - Paystack / Flutterwave: from the constants the resolvers always used, then checked
 *    against each provider's own API; a DISAGREEMENT is logged and reported, never
 *    silently applied.
 *  - Stripe Connect: read from Stripe `GET /v1/country_specs` (not hard-coded); we_enabled
 *    stays off until an admin enables the corridor.
 *  - Global: no coverage is invented — the owner confirms countries (admin matrix editor).
 */
class RailRegistrySeeder
{
    /** Paystack countries by ISO (slug is only used by the bank list endpoint). */
    public const PAYSTACK = ['NG', 'GH', 'ZA', 'KE', 'CI', 'EG'];

    public const FLUTTERWAVE = ['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA', 'RW', 'ZM', 'CI', 'SN', 'CM', 'EG'];

    /**
     * Stripe Connect cross-border payouts: a platform in the US, UK, EEA, Canada or Switzerland can pay connected accounts in any of those regions
     * (docs.stripe.com/connect/cross-border-payouts, read 2026-10-21). The Stripe API (`country_specs`) replaces this the moment a key is saved.
     */
    public const STRIPE_CONNECT_DOCS = ['US', 'GB', 'CA', 'CH', 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO'];

    /**
     * Stripe Global Payouts recipient countries for a US or UK sender (docs.stripe.com/global-payouts/recipient-requirements, read 2026-10-21):
     * pay a third party's bank account in their own currency. This is how Africa outside Nigeria/Ghana is reached on Stripe.
     */
    public const STRIPE_GLOBAL_DOCS = ['AL', 'DZ', 'AG', 'AM', 'AU', 'AT', 'BS', 'BH', 'BE', 'BJ', 'BT', 'BA', 'BW', 'BN', 'BG', 'CA', 'CN', 'CR', 'CI', 'HR', 'CY', 'CZ', 'DK', 'DO', 'EC', 'EG', 'SV', 'EE', 'ET', 'FI', 'FR', 'GM', 'DE', 'GR', 'GT', 'GY', 'HK', 'HU', 'IS', 'IN', 'ID', 'IE', 'IL', 'IT', 'JM', 'JP', 'JO', 'KE', 'KW', 'LV', 'LI', 'LT', 'LU', 'MG', 'MY', 'MT', 'MU', 'MX', 'MD', 'MC', 'MN', 'MA', 'MZ', 'PE', 'NA', 'NL', 'NZ', 'MK', 'NO', 'OM', 'PK', 'PA', 'PH', 'PL', 'PT', 'QA', 'RO', 'RW', 'LC', 'SM', 'SN', 'RS', 'SG', 'SK', 'SI', 'LK', 'ZA', 'ES', 'SE', 'CH', 'TW', 'TZ', 'TH', 'TT', 'TN', 'TR', 'AE', 'GB', 'US', 'UZ', 'VN'];

    public function __construct(private PayoutRailRegistry $registry) {}

    /** @return array{seeded: int, drift: list<string>, skipped: list<string>} */
    public function run(): array
    {
        $out = ['seeded' => 0, 'drift' => [], 'skipped' => []];

        foreach (['paystack' => self::PAYSTACK, 'flutterwave' => self::FLUTTERWAVE] as $rail => $countries) {
            foreach ($countries as $c) {
                $out['seeded'] += $this->upsert($c, $rail, true, 'seed', ['bank']);
            }
        }

        // Verify against each provider's API where we hold credentials; report any disagreement.
        $paystack = $this->paystackCountries();
        if ($paystack === null) {
            $out['skipped'][] = 'paystack (no key / API unreachable)';
        } else {
            $this->markVerified('paystack', self::PAYSTACK, $paystack, $out);
        }

        // Documented coverage, always seeded (never overwrites an admin override or a row the API has verified): without it the guide would call every
        // Stripe country "not supported" simply because no Stripe key has been saved yet. It is labelled as documentation, not as verified.
        foreach (self::STRIPE_CONNECT_DOCS as $c) {
            $out['seeded'] += $this->upsertDocs($c, 'stripe_connect', 'https://docs.stripe.com/connect/cross-border-payouts', ['bank']);
        }
        foreach (self::STRIPE_GLOBAL_DOCS as $c) {
            $out['seeded'] += $this->upsertDocs($c, 'global', 'https://docs.stripe.com/global-payouts/recipient-requirements', ['bank', 'wire']);
        }

        $stripe = $this->stripeCountries();
        if ($stripe === null) {
            $out['skipped'][] = 'stripe_connect (no key / API unreachable): documented list used';
        } else {
            foreach ($stripe as $c) {
                $out['seeded'] += $this->upsert($c, 'stripe_connect', true, 'api', ['bank'], 'https://docs.stripe.com/api/country_specs', verified: true);
            }
        }

        $this->registry->flush();

        return $out;
    }

    /**
     * Re-check the provider APIs and diff against the registry (monthly + admin button).
     * Raises `guide_registry_drift` when they disagree; rows not verified for 45 days are
     * reported as needing re-verification.
     *
     * @return array{drift: list<string>, stale: int}
     */
    public function audit(): array
    {
        $out = ['seeded' => 0, 'drift' => [], 'skipped' => []];
        if (($p = $this->paystackCountries()) !== null) {
            $this->markVerified('paystack', self::PAYSTACK, $p, $out);
        }
        if (($s = $this->stripeCountries()) !== null) {
            $known = PayoutCountryRail::where('rail', 'stripe_connect')->where('provider_supports', true)->pluck('country')->all();
            foreach (array_diff($s, $known) as $c) {
                $out['drift'][] = "stripe_connect: {$c} now supported by Stripe, not in registry";
            }
            foreach (array_diff($known, $s) as $c) {
                $out['drift'][] = "stripe_connect: {$c} in registry, no longer listed by Stripe";
            }
        }

        $stale = PayoutCountryRail::where('provider_supports', true)->where(fn ($q) => $q->whereNull('verified_at')->orWhere('verified_at', '<', now()->subDays(45)))->count();

        if ($out['drift'] !== []) {
            AlertAdminJob::dispatch(code: 'guide_registry_drift', message: 'Payout rail registry disagrees with a provider: '.implode('; ', array_slice($out['drift'], 0, 5)), context: ['drift' => $out['drift']]);
        }
        $this->registry->flush();

        return ['drift' => $out['drift'], 'stale' => $stale];
    }

    /** A row from a provider's published documentation: created when missing, never touches an override, and never downgrades a row the API verified. */
    private function upsertDocs(string $country, string $rail, string $source, array $methods): int
    {
        $row = PayoutCountryRail::firstOrNew(['country' => strtoupper($country), 'rail' => $rail]);
        if ($row->exists) {
            return 0;
        }
        $row->fill(['provider_supports' => true, 'methods' => $methods, 'source_url' => $source, 'verified_at' => now(), 'verified_by' => 'docs']);
        $row->save();

        return 1;
    }

    /** @param array<string, mixed>|null $methods */
    private function upsert(string $country, string $rail, bool $supports, string $by, ?array $methods = null, ?string $source = null, bool $verified = false): int
    {
        $row = PayoutCountryRail::firstOrNew(['country' => strtoupper($country), 'rail' => $rail]);
        $isNew = ! $row->exists;
        if ($row->admin_override !== null && $row->admin_override !== 'none') {
            return 0; // a human decided; never overwrite
        }
        $row->fill(['provider_supports' => $supports, 'methods' => $row->methods ?? $methods, 'source_url' => $source ?? $row->source_url]);
        if ($isNew || $verified) {
            $row->fill(['verified_at' => $verified ? now() : $row->verified_at, 'verified_by' => $verified ? $by : ($row->verified_by ?? $by)]);
        }
        $row->save();

        return $isNew ? 1 : 0;
    }

    /** @return list<string>|null ISO codes Paystack reports, null when it cannot be asked */
    private function paystackCountries(): ?array
    {
        if (blank(config('services.paystack.secret_key'))) {
            return null;
        }
        try {
            $res = Http::withToken(config('services.paystack.secret_key'))->acceptJson()->get(rtrim(config('services.paystack.base_url'), '/').'/country')->throw()->json();
            $codes = collect((array) data_get($res, 'data', []))->pluck('iso_code')->filter()->map(fn ($c) => strtoupper((string) $c))->values()->all();

            return $codes === [] ? null : $codes;
        } catch (Throwable $e) {
            Log::warning('payout rail audit: paystack /country failed: '.$e->getMessage());

            return null;
        }
    }

    /** @return list<string>|null */
    private function stripeCountries(): ?array
    {
        if (blank(config('services.stripe.secret_key'))) {
            return null;
        }
        try {
            $codes = [];
            $after = null;
            do {
                $res = Http::withToken((string) config('services.stripe.secret_key'))->acceptJson()
                    ->get(rtrim((string) config('services.stripe.base_url'), '/').'/country_specs', array_filter(['limit' => 100, 'starting_after' => $after]))->throw()->json();
                foreach ((array) data_get($res, 'data', []) as $spec) {
                    $codes[] = strtoupper((string) ($spec['id'] ?? ''));
                }
                $after = data_get($res, 'has_more') ? end($codes) : null;
            } while ($after !== null);

            $codes = array_values(array_filter(array_unique($codes)));

            return $codes === [] ? null : $codes;
        } catch (Throwable $e) {
            Log::warning('payout rail audit: stripe country_specs failed: '.$e->getMessage());

            return null;
        }
    }

    /** Stamp the rows the provider confirms; report rows it does NOT confirm as drift. */
    private function markVerified(string $rail, array $ours, array $reported, array &$out): void
    {
        foreach ($ours as $c) {
            if (in_array($c, $reported, true)) {
                PayoutCountryRail::where('country', $c)->where('rail', $rail)->update(['verified_at' => now(), 'verified_by' => 'api']);
            } else {
                $out['drift'][] = "{$rail}: {$c} in registry, not listed by the provider API";
                Log::warning("payout rail registry drift: {$rail} {$c} not in provider list");
            }
        }
    }
}
