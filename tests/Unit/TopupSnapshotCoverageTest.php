<?php

namespace Tests\Unit;

use App\Services\Topup\TopupSnapshot;
use Tests\TestCase;

/** A fresh install must start with real offers from every supplier we sell through, not only the first one. */
class TopupSnapshotCoverageTest extends TestCase
{
    /** @return array<string, int> provider => rows for NG */
    private function ngByProvider(): array
    {
        $d = json_decode((string) gzdecode((string) file_get_contents(dirname(__DIR__, 2).'/database/seeders/topup-offers.json.gz')), true);
        $f = array_flip($d['fields']);
        $out = [];
        foreach ($d['rows'] as $r) {
            if ($r[$f['country_iso']] === 'NG') {
                $out[$r[$f['provider_code']]] = ($out[$r[$f['provider_code']]] ?? 0) + 1;
            }
        }

        return $out;
    }

    public function test_shipped_snapshot_has_zendit_ding_and_paga_offers_for_nigeria(): void
    {
        $by = $this->ngByProvider();
        foreach (['zendit', 'ding', 'paga'] as $p) {
            $this->assertGreaterThan(20, $by[$p] ?? 0, "snapshot has too few $p offers for NG");
        }
        $this->assertTrue(is_file(TopupSnapshot::path()));
    }
}
