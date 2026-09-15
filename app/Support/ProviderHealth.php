<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Provider health across the WHOLE eSIM + number stack (BUILD-5 §2). The old
 * providers:health-check only pinged three wallet-funded providers; this covers
 * every Active provider in both stacks.
 *
 * The probe IS the reachability check: calling a provider's balance endpoint
 * hits its live API, so a provider that is down/erroring surfaces as `down`
 * with the error, not silently. Providers with no wallet-balance concept
 * (Twilio/Telnyx — permanent/voice) are reported `configured` with no live
 * balance probe rather than faked.
 *
 * Results are cached under the same key the admin dashboard already reads, so
 * the existing "Provider wallets" widget keeps working and simply shows more.
 *
 * Prompt 12 — adding a provider to PROVIDERS below also requires adding it
 * to: `PermanentNumberRouter::$lane`, `ProviderModels::MODELS[...]['lane']`
 * (+ `PROVIDER_KEY_FIELD`), `SmsInboundWebhookController::PROVIDERS`.
 */
class ProviderHealth
{
    public const CACHE_KEY = 'providers:health';

    /**
     * provider => [container binding, low-balance Setting key, stack].
     * The probe method is auto-detected (getBalance for eSIM, balance for
     * number providers) so we never call a method a service doesn't have.
     *
     * @var array<string, array{0:string,1:?string,2:string}>
     */
    private const PROVIDERS = [
        // eSIM stack — every provider implements EsimProviderInterface::getBalance().
        'esimgo' => ['esim.esimgo', 'pricing.low_balance_alert.esimgo', 'esim'],
        'airalo' => ['esim.airalo', 'pricing.low_balance_alert.airalo', 'esim'],
        'quibity' => ['esim.quibity', 'pricing.low_balance_alert.quibity', 'esim'],
        'zendit' => ['esim.zendit', 'pricing.low_balance_alert.zendit', 'esim'],
        'oneglobal' => ['esim.oneglobal', 'pricing.low_balance_alert.oneglobal', 'esim'],
        'montymobile' => ['esim.montymobile', 'pricing.low_balance_alert.montymobile', 'esim'],
        'gigs' => ['esim.gigs', 'pricing.low_balance_alert.gigs', 'esim'],
        // Number stack — SMS/OTP providers implement SmsProviderInterface::balance().
        'getatext' => ['number.getatext', 'pricing.low_balance_alert.getatext', 'number'],
        'fivesim' => ['number.fivesim', 'pricing.low_balance_alert.fivesim', 'number'],
        'herosms' => ['number.herosms', 'pricing.low_balance_alert.herosms', 'number'],
        'virtsms' => ['number.virtsms', 'pricing.low_balance_alert.virtsms', 'number'],
        // Owner audit (2026-09-15) — SMSPool/OnlineSIM were live-key-capable
        // but had zero health monitoring; both implement balance().
        'smspool' => ['number.smspool', 'pricing.low_balance_alert.smspool', 'number'],
        'onlinesim' => ['number.onlinesim', 'pricing.low_balance_alert.onlinesim', 'number'],
        // Permanent/voice — no prepaid wallet balance to read; reachability only.
        'twilio' => ['number.twilio', null, 'number'],
        'telnyx' => ['number.telnyx', null, 'number'],
        'plivo' => ['number.plivo', null, 'number'],
        'vonage' => ['number.vonage', null, 'number'],
        'sinch' => ['number.sinch', null, 'number'],
        // Sonetel (owner audit, 2026-09-15) has no prepaid-balance concept
        // (OAuth account, not a wallet) — reachability only, same as Twilio/Telnyx.
        'sonetel' => ['number.sonetel', null, 'number'],
    ];

    /**
     * Probe every provider and return a status map. Shape per provider:
     * ['status' => ok|low|down|configured|coming_soon, 'balance' => ?float,
     *  'stack' => esim|number, 'error' => ?string, 'checked_at' => string,
     *  'last_success_at' => ?string].
     *
     * @return array<string, array<string, mixed>>
     */
    public function checkAll(): array
    {
        $previous = $this->cached();
        $health = [];
        // Owner request (2026-09-15) — one query for every paused provider so
        // probe() never fires a live call (or an alert) for something an admin
        // deliberately took out of rotation.
        $paused = \App\Models\ProviderRegistry::whereNotNull('paused_at')->pluck('provider_key')->all();

        foreach (self::PROVIDERS as $provider => [$binding, $alertKey, $stack]) {
            if (in_array($provider, $paused, true)) {
                $health[$provider] = ['status' => 'paused', 'stack' => $stack, 'balance' => null,
                    'checked_at' => now()->toDateTimeString(),
                    'last_success_at' => $previous[$provider]['last_success_at'] ?? null];

                continue;
            }
            $health[$provider] = $this->probe($provider, $binding, $alertKey, $stack, $previous[$provider] ?? []);
        }

        Cache::put(self::CACHE_KEY, $health, now()->addMinutes(30));

        // BUILD-14 §3.1 — ALSO persist each probe into the Provider Registry (in
        // addition to the cache write above, never instead of it, so the existing
        // "Provider wallets" dashboard widget keeps working unchanged).
        $this->upsertRegistry($health);

        // BUILD-16 §1 — a periodic signal NCI refreshes from (queued listener).
        \App\Events\HealthCheckCompleted::dispatch(array_keys($health));

        return $health;
    }

    /**
     * Persist the live-truth snapshot into provider_registry, keyed on
     * provider_key. Metadata (stack, product families, onboarding tier, URLs) is
     * owned by the seeder; here we only touch the live-truth columns + the
     * enabled mirror, then bust the registry snapshot cache so a fresh result is
     * visible within its short TTL.
     *
     * @param array<string, array<string, mixed>> $health
     */
    private function upsertRegistry(array $health): void
    {
        foreach ($health as $provider => $info) {
            try {
                $status = (string) ($info['status'] ?? 'coming_soon');
                $meta = \App\Models\ProviderRegistry::deriveMeta($provider);

                $live = [
                    'status' => $status,
                    'balance' => $info['balance'] ?? null,
                    'latency_ms' => $info['latency_ms'] ?? null,
                    'last_checked_at' => now(),
                    'enabled' => ProviderStatus::isActive($provider),
                ];
                if (in_array($status, ['ok', 'low'], true)) {
                    $live['last_success_at'] = now();
                } elseif ($status === 'down') {
                    $live['last_failure_at'] = now();
                    $live['last_error'] = $info['error'] ?? null;
                }

                \App\Models\ProviderRegistry::updateOrCreate(
                    ['provider_key' => $provider],
                    // On first sight (unseeded) fill the derived metadata too, so a
                    // provider is never a bare row; the seeder later enriches URLs.
                    $live + ['stack' => $meta['stack'], 'product_families' => $meta['product_families']],
                );
            } catch (\Throwable) {
                // Best-effort: a registry write must never break the health check
                // (which still alerts + updates the cache regardless).
            }
        }

        \App\Models\ProviderRegistry::flushSnapshot();
    }

    /** @param array<string, mixed> $prev */
    private function probe(string $provider, string $binding, ?string $alertKey, string $stack, array $prev): array
    {
        $now = now()->toDateTimeString();
        $base = ['stack' => $stack, 'balance' => null, 'checked_at' => $now,
            'last_success_at' => $prev['last_success_at'] ?? null];

        if (! ProviderStatus::isActive($provider)) {
            return ['status' => 'coming_soon'] + $base;
        }

        $service = app($binding);
        $method = method_exists($service, 'getBalance') ? 'getBalance'
            : (method_exists($service, 'balance') ? 'balance' : null);

        // No wallet-balance concept (e.g. Twilio/Telnyx): it's configured, but we
        // don't fake a live probe we can't do cheaply.
        if ($method === null) {
            return ['status' => 'configured'] + $base;
        }

        // Time the live probe itself (BUILD-14 §3.2 — latency was never recorded).
        $start = microtime(true);
        try {
            $balance = (float) $service->{$method}();
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $start) * 1000)] + $base;
        }
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        $threshold = $alertKey !== null ? (float) Setting::getValue($alertKey, 0) : 0.0;
        $low = $threshold > 0 && $balance < $threshold;

        return [
            'status' => $low ? 'low' : 'ok',
            'balance' => $balance,
            'stack' => $stack,
            'latency_ms' => $latencyMs,
            'checked_at' => $now,
            'last_success_at' => $now,
        ] + ($low ? ['threshold' => $threshold] : []);
    }

    /** @return array<string, array<string, mixed>> */
    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    /** Providers whose last probe put them in a bad state (down or low). */
    public function unhealthy(): array
    {
        return array_filter($this->cached(), fn ($h) => in_array($h['status'] ?? '', ['down', 'low'], true));
    }
}
