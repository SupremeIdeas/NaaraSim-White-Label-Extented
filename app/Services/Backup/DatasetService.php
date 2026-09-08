<?php

namespace App\Services\Backup;

use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * Portable dataset export/import (blueprint Section 28) — move reference data
 * (catalogue, settings) between environments. Import ALWAYS supports a dry-run
 * that reports what would change without writing, and the real import runs
 * inside a single transaction so a mid-way failure rolls everything back.
 *
 * Only an allow-list of non-PII, non-money reference tables is portable; each
 * has a natural key so re-importing is idempotent (existing rows are skipped,
 * never overwritten).
 */
class DatasetService
{
    /** table => natural key columns used to detect duplicates. */
    public const TABLES = [
        'settings' => ['key'],
        'esim_plans' => ['provider', 'provider_plan_id'],
    ];

    public static function exportableTables(): array
    {
        return array_keys(self::TABLES);
    }

    /**
     * @param  list<string>  $tables
     */
    public function export(array $tables): string
    {
        $tables = array_values(array_intersect($tables, self::exportableTables()));

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $payload['tables'][$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Import a dataset. In dry-run mode nothing is written — the returned
     * summary reports how many rows are new vs already present.
     *
     * @return array<string,array{new:int,existing:int}>
     */
    public function import(string $json, bool $dryRun = true): array
    {
        $data = json_decode($json, true);
        abort_if(! is_array($data) || ! isset($data['tables']), 422, 'This file is not a NaaraSim dataset.');

        $plan = $this->plan($data['tables']);

        if ($dryRun) {
            return $this->summary($plan);
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $table => $rows) {
                foreach ($rows['insert'] as $row) {
                    DB::table($table)->insert($row);
                }
            }
        });

        Auditor::log('dataset.imported', null, null, $this->summary($plan));

        return $this->summary($plan);
    }

    /**
     * Build, per table, the rows that would be inserted vs skipped — without
     * touching the database. Unknown tables are rejected outright.
     */
    private function plan(array $tables): array
    {
        $plan = [];

        foreach ($tables as $table => $rows) {
            abort_unless(array_key_exists($table, self::TABLES), 422, "Table not importable: {$table}");
            $keys = self::TABLES[$table];

            $insert = [];
            $existing = 0;

            foreach ($rows as $row) {
                $row = (array) $row;
                $query = DB::table($table);
                foreach ($keys as $key) {
                    $query->where($key, $row[$key] ?? null);
                }

                if ($query->exists()) {
                    $existing++;

                    continue;
                }

                unset($row['id']); // let the target assign its own primary key
                $insert[] = $row;
            }

            $plan[$table] = ['insert' => $insert, 'existing' => $existing];
        }

        return $plan;
    }

    private function summary(array $plan): array
    {
        $summary = [];
        foreach ($plan as $table => $rows) {
            $summary[$table] = ['new' => count($rows['insert']), 'existing' => $rows['existing']];
        }

        return $summary;
    }
}
