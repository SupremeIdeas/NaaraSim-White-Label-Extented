<?php

namespace Tests\Unit;

use App\Support\SupplierScrub;
use PHPUnit\Framework\TestCase;

/**
 * Supplier-identity scrub (rule 1.2): a provider's own brand must never survive
 * into a user-facing plan name, but the generic word "eSIM" is kept.
 */
class SupplierScrubTest extends TestCase
{
    public function test_it_removes_supplier_brands_but_keeps_generic_words(): void
    {
        $this->assertSame('1GB Europe', SupplierScrub::name('Airalo 1GB Europe'));
        $this->assertSame('Europe 5GB', SupplierScrub::name('eSIM Go — Europe 5GB'));
        $this->assertSame('Unlimited data', SupplierScrub::name('Zendit Unlimited data'));
        $this->assertSame('Global 10GB', SupplierScrub::name('Quibity | Global 10GB'));
        $this->assertSame('Nigeria Voice', SupplierScrub::name('Monty Mobile Nigeria Voice'));

        // The standalone word "eSIM" is legitimate and preserved.
        $this->assertSame('eSIM 3GB Nigeria', SupplierScrub::name('eSIM 3GB Nigeria'));
    }

    public function test_a_name_that_is_only_a_brand_falls_back_to_a_neutral_label(): void
    {
        $this->assertSame('eSIM plan', SupplierScrub::name('Airalo'));
        $this->assertSame('eSIM plan', SupplierScrub::name('  zendit  '));
    }
}
