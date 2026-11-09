<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;
use App\Support\Topup\TopupBrandTint;
use App\Support\TopupLogos;
use App\Support\TopupSettings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * "Pay with credit" offers: real data bundles from the live catalogue with the discount a customer's credit can take off them.
 * The discount is TopupCredit::ceiling (a slice of OUR profit, never the margin), so a card can never promise more than the quote will give.
 * Cost never leaves this class: a card carries the customer price, the saving and the percentage only.
 */
final class TopupOffers
{
    /** Countries that have data bundles on sale, in the order a customer expects: the popular ones first, then A to Z. @return list<string> */
    public function countries(): array
    {
        $v = (int) Cache::get('topup.offers.v', 0);

        return Cache::remember("topup.offers.countries.$v", 600, function () {
            $router = app(ProviderRouter::class);
            $have = TopupProduct::query()->where('active', true)->where('type', 'data')->where('is_fixed', true)->distinct()->pluck('country_iso')
                ->map(fn ($c) => strtoupper((string) $c))->filter(fn ($c) => $router->sellable($c))->values()->all();
            $popular = ['NG', 'GH', 'KE', 'ZA', 'UG', 'TZ', 'US', 'GB', 'IN', 'AE', 'BR', 'MX', 'PH', 'PK', 'ID', 'BD', 'DE', 'FR', 'TR'];
            $first = array_values(array_intersect($popular, $have));
            $rest = array_values(array_diff($have, $first));
            usort($rest, fn ($x, $y) => strcmp(\App\Support\CountryNames::name($x), \App\Support\CountryNames::name($y)));

            return array_merge($first, $rest);
        });
    }

    private static function ids(string $key): array
    {
        return array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', (string) TopupSettings::get($key)) ?: [])));
    }

    /** Admin saved a change: drop every cached strip. */
    public static function flush(): void
    {
        Cache::increment('topup.offers.v') ?: Cache::forever('topup.offers.v', 1);
    }

    /** @return list<array{id:int,operator:string,country:string,title:string,validity:?string,price:float,save:float,pct:float,logo:?string,bg:?string,tint:string,ink:string,end:string,hidden:bool,pinned:bool}> */
    public function for(string $country, int $limit = 0, bool $forAdmin = false): array
    {
        $country = strtoupper($country ?: 'NG');
        if (! $forAdmin && ! TopupSettings::bool('offers_enabled')) {
            return [];
        }
        $limit = $limit ?: max(1, min(24, TopupSettings::int('offers_max')));
        $v = (int) Cache::get('topup.offers.v', 0);

        return Cache::remember("topup.offers.s3.$v.$country.$limit.".($forAdmin ? 'a' : 'u'), 600, fn () => $this->build($country, $limit, $forAdmin));
    }

    private function build(string $country, int $limit, bool $forAdmin): array
    {
        $router = app(ProviderRouter::class);
        if (! $router->sellable($country)) {
            return [];
        }
        $svc = app(QuoteService::class);
        $hidden = self::ids('offers_hidden');
        $pinned = self::ids('offers_pinned');
        $rows = [];
        $products = TopupProduct::query()->where('active', true)->where('country_iso', $country)->where('type', 'data')->where('is_fixed', true)->limit(400)->get();
        foreach ($products as $p) {
            if (! in_array($p->provider_code, $router->routedCodes($country), true) || ! $router->eligible($p->provider_code)) {
                continue;
            }
            $isHidden = in_array($p->id, $hidden, true);
            if ($isHidden && ! $forAdmin) {
                continue;
            }
            try {
                $q = $svc->price($p, (float) $p->fixed_amount, offer: true);
            } catch (Throwable) {
                continue;
            }
            $save = TopupCredit::ceiling((float) $q['price'], (float) $q['cost']);
            if (($save < 0.02 && ! in_array($p->id, $pinned, true)) || $q['price'] <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $p->id, 'operator' => $p->operator_name, 'country' => $country,
                'title' => $this->title($p), 'validity' => $p->validity_text,
                'price' => round((float) $q['price'], 2), 'save' => $save, 'pct' => $q['price'] > 0 ? round($save / (float) $q['price'] * 100, 1) : 0.0,
                'logo' => TopupLogos::url($p->operator_name, $country), 'bg' => TopupLogos::bg($p->operator_name, $country),
                'tint' => $tint = TopupBrandTint::for($p->operator_name, $country), 'ink' => TopupBrandTint::ink($tint), 'end' => TopupBrandTint::gradientEnd($tint), 'hidden' => $isHidden, 'pinned' => in_array($p->id, $pinned, true),
            ];
        }
        // Pinned first. Then one best offer from EACH network family (MTN, Glo, Airtel, 9mobile... each in its own colours), then second-best from each, and so on,
        // so the strip shows every network users know instead of the one with the best percentage.
        $pinned = array_values(array_filter($rows, fn ($r) => $r['pinned']));
        $byFam = [];
        foreach ($rows as $r) {
            if (! $r['pinned']) {
                $byFam[TopupBrandTint::family($r['operator'], $country)][] = $r;
            }
        }
        foreach ($byFam as &$list) {
            usort($list, fn ($a, $b) => $b['pct'] <=> $a['pct']);
        }
        unset($list);
        // Networks with the most offers first, so the big ones lead.
        uasort($byFam, fn ($a, $b) => count($b) <=> count($a));
        $out = $pinned;
        for ($round = 0; $round < 3 && count($out) < $limit; $round++) {
            foreach ($byFam as $list) {
                if (isset($list[$round]) && count($out) < $limit) {
                    $out[] = $list[$round];
                }
            }
        }
        $out = array_slice($out, 0, $limit);

        return $out;
    }

    private function title(TopupProduct $p): string
    {
        if ($p->data_unlimited) {
            return 'Unlimited data';
        }
        $mb = (float) $p->data_mb;
        if ($mb >= 1024) {
            return rtrim(rtrim(number_format($mb / 1024, 2), '0'), '.').' GB';
        }

        return $mb > 0 ? (int) $mb.' MB' : (string) $p->name;
    }
}
