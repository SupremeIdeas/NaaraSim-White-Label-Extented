<?php

namespace App\Support;

/**
 * Connectivity "Models" — the supplier-masking layer (roadmap: NaaraSim Wizard).
 *
 * NaaraSim never exposes which third-party actually supplies a service. Instead
 * each capability is a public **Model** backed by a lane of real providers (the
 * same lanes SmsNumberRouter / ProviderRouter already fail over across). Users,
 * the dashboard, and the future Wizard see the Model; internal routing uses the
 * real provider. A Model is only "live" when at least one provider in its lane
 * has its API key configured (via ProviderKeys) and — for permanent numbers —
 * once that flow is actually wired.
 *
 * This is the keystone the Wizard + reorganised dashboard build on. It is pure,
 * cached-free data + logic; it touches no money path.
 */
class ProviderModels
{
    /**
     * The four Models, mapped 1:1 to the real product types. Each lane lists the
     * INTERNAL provider keys (never shown to users), ordered as the routers try
     * them. `coming_soon` marks a capability whose purchase flow isn't wired yet.
     *
     * @var array<string, array{name:string, tagline:string, icon:string, caps:list<string>, lane:list<string>, coming_soon:bool}>
     */
    public const MODELS = [
        'naara_data' => [
            'name' => 'Naara Data',
            'tagline' => 'Global eSIM data — 190+ countries',
            'icon' => 'globe',
            'caps' => ['esim_data'],
            'lane' => ['esimgo', 'airalo', 'quibity'],
            'coming_soon' => false,
        ],
        'naara_connect' => [
            'name' => 'Naara Connect',
            'tagline' => 'Full eSIM — calls + data on one eSIM',
            'icon' => 'signal',
            'caps' => ['esim_voice'],
            'lane' => ['zendit', 'oneglobal', 'montymobile', 'gigs'],
            'coming_soon' => false,
        ],
        'naara_verify' => [
            'name' => 'Naara Verify',
            'tagline' => 'OTP & verification codes',
            'icon' => 'shield-check',
            'caps' => ['otp'],
            'lane' => ['getatext', 'fivesim', 'herosms', 'virtsms'],
            'coming_soon' => false,
        ],
        'naara_rent' => [
            'name' => 'Naara Rent',
            'tagline' => 'Short-term rental numbers',
            'icon' => 'hash',
            'caps' => ['rental'],
            'lane' => ['getatext', 'fivesim', 'herosms', 'virtsms'],
            'coming_soon' => false,
        ],
        'naara_line' => [
            'name' => 'Naara Line',
            'tagline' => 'Permanent number + voice',
            'icon' => 'phone',
            'caps' => ['permanent', 'voice', 'number_search'],
            'lane' => ['twilio', 'telnyx'],
            // Provisioning + monthly billing are now wired (PermanentNumberRouter +
            // virtual:renew). Availability is key-driven: live once Twilio or Telnyx
            // is configured, else needs_key.
            'coming_soon' => false,
        ],
    ];

    /** Internal provider key => the ProviderKeys field that proves it's configured. */
    private const PROVIDER_KEY_FIELD = [
        'esimgo' => 'esimgo_api_key',
        'airalo' => 'airalo_client_id',
        'quibity' => 'quibity_api_key',
        'zendit' => 'zendit_api_key',
        'oneglobal' => 'oneglobal_client_id',
        'montymobile' => 'montymobile_api_key',
        'gigs' => 'gigs_api_key',
        'getatext' => 'getatext_api_key',
        'fivesim' => 'fivesim_api_key',
        'herosms' => 'herosms_api_key',
        'virtsms' => 'virtsms_api_key',
        'twilio' => 'twilio_account_sid',
        'telnyx' => 'telnyx_api_key',
    ];

    /** Number type (NumberRequest) => Model key. */
    private const TYPE_MODEL = [
        'otp' => 'naara_verify',
        'rental' => 'naara_rent',
        'permanent' => 'naara_line',
    ];

    /** A Model definition + its key, or null if the key is unknown. */
    public static function find(string $key): ?array
    {
        return isset(self::MODELS[$key]) ? self::brandize(['key' => $key] + self::MODELS[$key]) : null;
    }

    /** Every Model, each with a live `status` (live | coming_soon | needs_key). */
    public static function all(): array
    {
        return array_map(
            fn ($key) => self::brandize(['key' => $key] + self::MODELS[$key] + ['status' => self::status($key)]),
            array_keys(self::MODELS),
        );
    }

    /**
     * Apply the white-label brand word to a Model's user-facing text so
     * "Naara Rent" reads "{word} Rent" everywhere these Models render (My Lines,
     * badges, wizard, catalogue). A no-op on a default 'Naara' install.
     */
    private static function brandize(array $model): array
    {
        foreach (['name', 'tagline'] as $field) {
            if (isset($model[$field]) && is_string($model[$field])) {
                $model[$field] = \App\Support\BrandSettings::rebrand($model[$field]);
            }
        }

        return $model;
    }

    /** Only Models a user can actually reach right now (status = live). */
    public static function available(): array
    {
        return array_values(array_filter(self::all(), fn ($m) => $m['status'] === 'live'));
    }

    /**
     * A Model's status:
     *   - coming_soon: the capability isn't wired yet (e.g. permanent numbers),
     *   - needs_key:   no provider in its lane has an API key configured,
     *   - live:        ready to sell.
     */
    public static function status(string $key): string
    {
        $model = self::MODELS[$key] ?? null;
        if ($model === null) {
            return 'needs_key';
        }
        if ($model['coming_soon']) {
            return 'coming_soon';
        }

        return self::laneConfigured($model['lane']) ? 'live' : 'needs_key';
    }

    /** True if ANY provider in the lane is configured (one absent supplier is fine). */
    public static function laneConfigured(array $lane): bool
    {
        foreach ($lane as $provider) {
            if (self::providerConfigured($provider)) {
                return true;
            }
        }

        return false;
    }

    public static function providerConfigured(string $provider): bool
    {
        $field = self::PROVIDER_KEY_FIELD[$provider] ?? null;

        return $field !== null && ProviderKeys::hasValue($field);
    }

    /**
     * The public Model that owns an internal provider. For number providers that
     * serve several types (5sim/SMS-Activate/Getatext do OTP *and* rental), the
     * `$type` disambiguates; without it we fall back to the first lane match.
     */
    public static function forProvider(string $provider, ?string $type = null): ?array
    {
        if ($type !== null && isset(self::TYPE_MODEL[$type])) {
            return self::find(self::TYPE_MODEL[$type]);
        }
        foreach (self::MODELS as $key => $model) {
            if (in_array($provider, $model['lane'], true)) {
                return self::find($key);
            }
        }

        return null;
    }

    /** The Model for a number type (otp|rental|permanent). */
    public static function forNumberType(?string $type): ?array
    {
        return $type !== null && isset(self::TYPE_MODEL[$type])
            ? self::find(self::TYPE_MODEL[$type])
            : null;
    }

    /** The eSIM Model. */
    public static function esim(): array
    {
        return self::find('naara_data');
    }
}
