<?php

namespace App\Services\Topup;

use App\Services\Topup\Contracts\TopupProviderInterface;
use App\Services\Topup\Providers\BitrefillTopupProvider;
use App\Services\Topup\Providers\PagaTopupProvider;
use App\Services\Topup\Providers\DingProvider;
use App\Services\Topup\Providers\ReloadlyTopupProvider;
use App\Services\Topup\Providers\VtpassProvider;
use App\Services\Topup\Providers\ZenditTopupProvider;

/** The adapter registry. A container binding `topup.provider.{code}` overrides a class (tests bind fakes). */
class TopupProviders
{
    /** @var array<string, class-string<TopupProviderInterface>> */
    public const MAP = [
        'zendit' => ZenditTopupProvider::class,
        'vtpass' => VtpassProvider::class,
        'reloadly' => ReloadlyTopupProvider::class,
        'ding' => DingProvider::class,
        'bitrefill' => BitrefillTopupProvider::class,
        'paga' => PagaTopupProvider::class,
    ];

    /** Display + float defaults per supplier. */
    public const META = [
        'zendit' => ['name' => 'Zendit', 'currency' => 'USD', 'note' => 'Live offers for 100+ countries. Verified against the live API.'],
        'vtpass' => ['name' => 'VTpass', 'currency' => 'NGN', 'note' => 'Nigeria specialist (MTN, Airtel, Glo, 9mobile). Needs a VTpass key + whitelisted server IP.'],
        'reloadly' => ['name' => 'Reloadly', 'currency' => 'USD', 'note' => 'International + African local-currency top-ups. Uses your existing Reloadly client id/secret.'],
        'bitrefill' => ['name' => 'Bitrefill', 'currency' => 'USD', 'note' => 'Phone refills with number lookup, paid from your Bitrefill balance (same key as gift cards and eSIMs). Polled, no signed webhook.'],
        'paga' => ['name' => 'Paga', 'currency' => 'NGN', 'note' => 'Nigeria airtime and data (MTN, Airtel, Glo, 9mobile) from your Paga business wallet. Same keys as the Paga gateway; the server IP must be whitelisted by Paga; set your commission % in services.paga.airtime_discount_pct.'],
        'ding' => ['name' => 'Ding', 'currency' => 'USD', 'note' => 'Global coverage with number lookup. No webhook: polled.'],
    ];

    public static function codes(): array
    {
        return array_keys(self::MAP);
    }

    public static function get(string $code): TopupProviderInterface
    {
        if (app()->bound('topup.provider.'.$code)) {
            return app('topup.provider.'.$code);
        }

        return app(self::MAP[$code] ?? throw new TopupException('Unknown top-up supplier.'));
    }

    /** @return array<string, TopupProviderInterface> */
    public static function all(): array
    {
        $out = [];
        foreach (self::codes() as $c) {
            $out[$c] = self::get($c);
        }

        return $out;
    }
}
