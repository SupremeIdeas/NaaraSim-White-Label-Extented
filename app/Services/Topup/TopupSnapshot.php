<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;

/**
 * The shipped offer snapshot: every operator's airtime + data offers as of the last export, committed as database/seeders/topup-offers.json.gz so a
 * fresh install (or a fork) has a full catalogue before its first live fetch. Live offers always replace it: opening a country re-fetches from the
 * supplier, and `topup:run sync` refreshes every routed country. Export with `php artisan topup:run snapshot`.
 */
class TopupSnapshot
{
    private const FIELDS = ['provider_code', 'provider_sku', 'country_iso', 'operator_name', 'operator_code', 'type', 'name', 'validity_text', 'data_mb', 'data_unlimited', 'is_fixed',
        'min_amount', 'max_amount', 'fixed_amount', 'currency', 'cost_native', 'cost_ratio', 'cost_currency', 'meta'];

    public static function path(): string
    {
        return base_path('database/seeders/topup-offers.json.gz');
    }

    public function export(): int
    {
        $rows = [];
        foreach (TopupProduct::where('active', true)->orderBy('country_iso')->orderBy('operator_name')->orderBy('fixed_amount')->cursor() as $p) {
            $r = [];
            foreach (self::FIELDS as $f) {
                $v = $p->getAttribute($f);
                $r[] = is_bool($v) ? (int) $v : $v;
            }
            $rows[] = $r;
        }
        file_put_contents(self::path(), gzencode((string) json_encode(['fields' => self::FIELDS, 'at' => now()->toIso8601String(), 'rows' => $rows], JSON_UNESCAPED_UNICODE), 9));

        return count($rows);
    }

    /** Load the snapshot into an EMPTY catalogue only (never overwrites live data). @return int rows imported */
    public function import(bool $force = false): int
    {
        if (! is_file(self::path()) || (! $force && TopupProduct::query()->exists())) {
            return 0;
        }
        $d = json_decode((string) gzdecode((string) file_get_contents(self::path())), true);
        $n = 0;
        $now = now();
        foreach (array_chunk((array) ($d['rows'] ?? []), 400) as $chunk) {
            $batch = [];
            foreach ($chunk as $r) {
                $row = array_combine((array) $d['fields'], $r);
                $row['is_fixed'] = (int) $row['is_fixed'];
                $row['data_unlimited'] = (int) $row['data_unlimited'];
                $row['meta'] = $row['meta'] === null ? null : (is_array($row['meta']) ? json_encode($row['meta']) : $row['meta']);
                $row += ['active' => 1, 'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now];
                $batch[] = $row;
                $n++;
            }
            TopupProduct::upsert($batch, ['provider_code', 'provider_sku', 'country_iso'], array_values(array_diff(array_keys($batch[0]), ['provider_code', 'provider_sku', 'country_iso', 'created_at'])));
        }

        return $n;
    }
}
