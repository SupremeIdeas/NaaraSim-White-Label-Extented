<?php

namespace App\Models;

use App\Support\ProviderModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * NAARA-BUILD-14 — the one shared Provider Registry row per provider (esim / sms
 * / permanent). An operational SNAPSHOT, never the ultimate source of truth: the
 * live provider API is still authoritative at the moment of purchase (amendment
 * 1). ProviderHealth writes the live-truth fields; the Routing Engine (BUILD-15)
 * writes reliability + circuit_breaker_state; NCI (BUILD-16) writes its own
 * columns. Read side goes through snapshot() only.
 */
class ProviderRegistry extends Model
{
    protected $table = 'provider_registry';

    /** The short-TTL cache the Routing Engine reads (never a raw query in router code). */
    public const SNAPSHOT_KEY = 'provider_registry:snapshot';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'product_families' => 'array',
            'balance' => 'decimal:4',
            'success_rate_24h' => 'decimal:4',
            'nci_score' => 'float',
            'nci_confidence' => 'float',
            'nci_computed_at' => 'datetime',
            'enabled' => 'boolean',
            'paused_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    /** A durable admin pause (2026-09-15) — never self-heals, unlike a circuit
     *  breaker's cooldown; only an explicit resume lifts it. */
    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * The cached read layer (§4) — the ONLY sanctioned way the Routing Engine
     * reads the registry, so the caching discipline can't be bypassed by a raw
     * query. Short TTL because this data changes faster than a country list;
     * busted on every upsert (flushSnapshot) so a fresh health result is visible
     * within the TTL, not stuck for the 15-minute health interval.
     *
     * @return array<string, array<string, mixed>> keyed by provider_key
     */
    public static function snapshot(): array
    {
        return Cache::remember(self::SNAPSHOT_KEY, now()->addSeconds(45), function () {
            return static::query()->get()->keyBy('provider_key')
                ->map(fn (self $r) => $r->toArray())->all();
        });
    }

    public static function flushSnapshot(): void
    {
        Cache::forget(self::SNAPSHOT_KEY);
    }

    /**
     * Derive a provider's stack + product families from the existing lane
     * definitions (ProviderModels), rather than re-deciding it by hand — so the
     * registry never drifts from the Models it serves.
     *
     * @return array{stack:string, product_families:list<string>}
     */
    public static function deriveMeta(string $providerKey): array
    {
        $families = [];
        $isEsim = false;
        $isPermanent = false;

        foreach (ProviderModels::MODELS as $key => $model) {
            if (in_array($providerKey, $model['lane'] ?? [], true)) {
                $families[] = $key;
                $caps = $model['caps'] ?? [];
                if (in_array('esim_data', $caps, true) || in_array('esim_voice', $caps, true)) {
                    $isEsim = true;
                }
                if (in_array('permanent', $caps, true) || in_array('voice', $caps, true)) {
                    $isPermanent = true;
                }
            }
        }

        $stack = $isEsim ? 'esim' : ($isPermanent ? 'permanent' : 'sms');

        return ['stack' => $stack, 'product_families' => array_values(array_unique($families))];
    }
}
