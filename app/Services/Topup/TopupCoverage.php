<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;

/** How much airtime and data we can actually sell: offers, countries and networks per type, per supplier, and which African countries have no airtime yet. Pure read. */
class TopupCoverage
{
    /** ISO codes of the 54 African countries, used for the "gaps" list. */
    public const AFRICA = ['DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CV', 'CF', 'TD', 'KM', 'CG', 'CD', 'CI', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ', 'TG', 'TN', 'UG', 'ZM', 'ZW'];

    /** @return array{types: array<string, array{offers: int, countries: int, networks: int}>, suppliers: list<array<string, mixed>>, africaGaps: array{airtime: list<string>, data: list<string>}, africaCovered: array{airtime: int, data: int}} */
    public function report(): array
    {
        $base = fn () => TopupProduct::query()->where('active', true);
        $types = [];
        foreach (['airtime', 'data'] as $t) {
            $types[$t] = ['offers' => (int) $base()->where('type', $t)->count(), 'countries' => (int) $base()->where('type', $t)->distinct()->count('country_iso'),
                'networks' => (int) $base()->where('type', $t)->distinct()->count('operator_name')];
        }
        $suppliers = $base()->selectRaw('provider_code, type, count(*) as offers, count(distinct country_iso) as countries')->groupBy('provider_code', 'type')->orderBy('provider_code')->get()
            ->groupBy('provider_code')->map(fn ($rows, $code) => ['code' => $code, 'airtime' => (int) ($rows->firstWhere('type', 'airtime')->offers ?? 0), 'airtimeCountries' => (int) ($rows->firstWhere('type', 'airtime')->countries ?? 0),
                'data' => (int) ($rows->firstWhere('type', 'data')->offers ?? 0), 'dataCountries' => (int) ($rows->firstWhere('type', 'data')->countries ?? 0)])->values()->all();
        $have = fn (string $t) => $base()->where('type', $t)->distinct()->pluck('country_iso')->map(fn ($c) => strtoupper((string) $c))->all();
        $air = $have('airtime');
        $dat = $have('data');

        return ['types' => $types, 'suppliers' => $suppliers,
            'africaGaps' => ['airtime' => array_values(array_diff(self::AFRICA, $air)), 'data' => array_values(array_diff(self::AFRICA, $dat))],
            'africaCovered' => ['airtime' => count(array_intersect(self::AFRICA, $air)), 'data' => count(array_intersect(self::AFRICA, $dat))]];
    }
}
