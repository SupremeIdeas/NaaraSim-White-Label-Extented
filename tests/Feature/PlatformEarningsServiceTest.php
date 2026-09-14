<?php

namespace Tests\Feature;

use App\Models\PlatformEarning;
use App\Services\Platform\PlatformEarningsException;
use App\Services\Platform\PlatformEarningsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 21-EXT §5 — the single global platform-earnings ledger that holds
 * white-label license sale proceeds, kept deliberately separate from any
 * per-merchant earnings bucket or general platform-profit reporting.
 */
class PlatformEarningsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PlatformEarningsService
    {
        return app(PlatformEarningsService::class);
    }

    public function test_accrue_adds_to_the_running_balance_with_before_and_after(): void
    {
        $earning = $this->service()->accrue(1500.00, 'white_label_license', 'ref-1');

        $this->assertSame(PlatformEarning::ACCRUAL, $earning->type);
        $this->assertSame('1500.0000', (string) $earning->amount);
        $this->assertSame('1500.0000', (string) $earning->balance_after);
        $this->assertSame(1500.0, $this->service()->balance());

        $this->service()->accrue(2500.00, 'white_label_license', 'ref-2');
        $this->assertSame(4000.0, $this->service()->balance());
    }

    public function test_accrue_is_idempotent_on_reference(): void
    {
        $this->service()->accrue(1500.00, 'white_label_license', 'dup-ref');
        $this->service()->accrue(1500.00, 'white_label_license', 'dup-ref');

        $this->assertSame(1, PlatformEarning::count());
        $this->assertSame(1500.0, $this->service()->balance());
    }

    public function test_accrue_ignores_a_zero_or_negative_amount(): void
    {
        $this->assertNull($this->service()->accrue(0, 'white_label_license', 'zero-ref'));
        $this->assertNull($this->service()->accrue(-5, 'white_label_license', 'neg-ref'));
        $this->assertSame(0, PlatformEarning::count());
    }

    public function test_hold_reduces_the_balance_for_a_withdrawal(): void
    {
        $this->service()->accrue(5000.00, 'white_label_license', 'ref-1');

        $hold = $this->service()->hold(2000.00, 'withdraw-1');

        $this->assertSame(PlatformEarning::HOLD, $hold->type);
        $this->assertSame('-2000.0000', (string) $hold->amount);
        $this->assertSame(3000.0, $this->service()->balance());
    }

    public function test_hold_beyond_the_available_balance_throws_and_never_moves_it(): void
    {
        $this->service()->accrue(1000.00, 'white_label_license', 'ref-1');

        try {
            $this->service()->hold(1500.00, 'withdraw-1');
            $this->fail('holding more than the available balance should throw');
        } catch (PlatformEarningsException) {
            // expected
        }

        $this->assertSame(1000.0, $this->service()->balance());
    }

    public function test_release_returns_held_earnings_after_a_reversed_payout(): void
    {
        $this->service()->accrue(5000.00, 'white_label_license', 'ref-1');
        $this->service()->hold(2000.00, 'withdraw-1');

        $this->service()->release(2000.00, 'refund-withdraw-1');

        $this->assertSame(5000.0, $this->service()->balance());
    }

    public function test_balance_is_zero_before_anything_is_recorded(): void
    {
        $this->assertSame(0.0, $this->service()->balance());
    }
}
