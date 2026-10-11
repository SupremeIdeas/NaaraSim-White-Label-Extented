<?php

namespace Tests\Feature\Appearance;

use App\Console\Commands\NxCoverage;
use Tests\TestCase;

/** The coverage gate itself (Prompt 20 §23): converted views stay token-only, and the scanner catches what it should. */
class NxCoverageTest extends TestCase
{
    public function test_every_converted_batch_one_view_has_no_hard_coded_colour(): void
    {
        $this->artisan('nx:coverage', ['--batch' => 1])->assertSuccessful();
    }

    public function test_every_converted_batch_two_view_has_no_hard_coded_colour(): void
    {
        // Home, eSIM storefront, Wallet (+ Withdraw, payout guide, step-up) and Account & privacy.
        $this->artisan('nx:coverage', ['--batch' => 2])->assertSuccessful();
    }

    public function test_the_scanner_flags_hex_palette_brand_classes_and_per_element_dark_variants(): void
    {
        $scan = new NxCoverage;
        $found = $scan->scan('<div class="bg-slate-100 text-primary dark:bg-white/5" style="color:#abc123">x</div>');

        $this->assertContains('tailwind bg-slate-100', $found);
        $this->assertContains('brand class text-primary', $found);
        $this->assertContains('hex #abc123', $found);
        $this->assertNotEmpty(array_filter($found, fn ($f) => str_starts_with($f, 'per-element dark variant')));
    }

    public function test_tokens_comments_and_fenced_brand_artwork_are_not_offenders(): void
    {
        $scan = new NxCoverage;

        $this->assertSame([], $scan->scan('<div class="ns-card" style="color:rgb(var(--nx-text))">&#8226; ok</div>{{-- bg-slate-900 #fff --}}'));
        $this->assertSame([], $scan->scan("{{-- nx:allow:start brand promo --}}<p class=\"text-slate-300\">a</p>{{-- nx:allow:end --}}"));
    }

    public function test_a_view_counts_as_converted_only_when_it_renders_skin_components(): void
    {
        $scan = new NxCoverage;

        $this->assertTrue($scan->isConverted('<x-nx.page><x-nx.cta>Go</x-nx.cta></x-nx.page>'));
        $this->assertFalse($scan->isConverted('<div class="ns-card">raw markup</div>'));
        $this->assertFalse($scan->isConverted('<x-nx.icon name="x" />'), 'an icon alone does not make a screen skinned');
    }
}
