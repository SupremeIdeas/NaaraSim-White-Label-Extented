<?php

namespace Tests\Feature;

use App\Services\Pricing\TaxService;
use App\Support\TaxRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-7 §2: the tax support layer. Ships OFF — no tax anywhere until an admin
 * sets a country rate. Tax is additive and separate from cost/profit math.
 */
class TaxServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TaxRates::flush();
    }

    public function test_no_tax_is_charged_by_default_anywhere(): void
    {
        $tax = app(TaxService::class);
        $this->assertFalse(TaxRates::isConfigured());
        $this->assertSame(0.0, $tax->taxFor('GB', 100.0));
        $this->assertSame(0.0, $tax->taxFor('NG', 100.0));
        $this->assertFalse($tax->applies('GB'));
    }

    public function test_tax_applies_only_to_a_configured_country(): void
    {
        TaxRates::save(['GB' => 20]);
        $tax = app(TaxService::class);

        $this->assertSame(20.0, $tax->taxFor('GB', 100.0));
        $this->assertSame(20.0, $tax->taxFor('gb', 100.0)); // case-insensitive
        $this->assertTrue($tax->applies('GB'));

        // A country with no configured rate still pays nothing.
        $this->assertSame(0.0, $tax->taxFor('NG', 100.0));
        $this->assertSame(0.0, $tax->taxFor(null, 100.0));
    }

    public function test_saving_ignores_blank_and_zero_rates(): void
    {
        TaxRates::save(['GB' => 20, '' => 5, 'DE' => 0, 'FR' => 19]);
        $this->assertSame(['GB' => 20.0, 'FR' => 19.0], TaxRates::all());
    }
}
