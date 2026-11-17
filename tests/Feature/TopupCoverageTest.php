<?php

namespace Tests\Feature;

use App\Models\TopupProduct;
use App\Services\Topup\TopupCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Top-ups > Catalogue coverage card counts what the install can sell: offers, countries and networks per type, and the African countries with nothing yet. */
class TopupCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_counts_offers_countries_and_african_gaps(): void
    {
        foreach ([['NG', 'airtime'], ['NG', 'data'], ['GH', 'airtime']] as $i => [$iso, $t]) {
            TopupProduct::create(['provider_code' => 'ding', 'provider_sku' => 's'.$i, 'country_iso' => $iso, 'operator_name' => 'Op'.$i, 'type' => $t, 'name' => 'x', 'active' => true, 'is_fixed' => true, 'currency' => 'USD']);
        }
        $r = app(TopupCoverage::class)->report();
        $this->assertSame(2, $r['types']['airtime']['countries']);
        $this->assertSame(1, $r['types']['data']['countries']);
        $this->assertNotContains('NG', $r['africaGaps']['airtime']);
        $this->assertContains('KE', $r['africaGaps']['airtime']);
        $this->assertContains('GH', $r['africaGaps']['data']);
    }
}
