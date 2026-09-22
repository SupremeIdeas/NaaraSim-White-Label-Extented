<?php

namespace Tests\Feature;

use App\Models\ProviderOutcome;
use App\Services\NCI\NciScorer;
use App\Support\CountryPickerSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 10 (remaining item) — NciScorer::publicSuccessRate(), a read-only,
 * customer-safe projection surfaced on the Numbers CountryPicker. Deliberately
 * NOT the internal nci_score (which folds in the §6 margin tie-break and NCI
 * kill-switch/confidence weighting) — just the plain success/total ratio over
 * the trailing window, gated by a minimum sample so a handful of tries never
 * produces a misleadingly confident badge.
 */
class PublicSuccessRateTest extends TestCase
{
    use RefreshDatabase;

    private function outcomes(string $key, int $success, int $failure): void
    {
        foreach (range(1, $success) as $i) {
            ProviderOutcome::create(['provider_key' => $key, 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()]);
        }
        foreach (range(1, $failure) as $i) {
            ProviderOutcome::create(['provider_key' => $key, 'stack' => 'sms', 'outcome' => 'failure', 'occurred_at' => now()]);
        }
    }

    public function test_it_returns_null_below_the_minimum_sample(): void
    {
        $this->outcomes('fivesim', success: 10, failure: 5); // 15 total, below the threshold

        $this->assertNull(app(NciScorer::class)->publicSuccessRate('fivesim'));
    }

    public function test_it_returns_the_plain_ratio_at_or_above_the_minimum_sample(): void
    {
        $this->outcomes('fivesim', success: 18, failure: 2); // 20 total, at the threshold

        $this->assertSame(0.9, app(NciScorer::class)->publicSuccessRate('fivesim'));
    }

    public function test_it_ignores_outcomes_outside_the_trailing_window(): void
    {
        foreach (range(1, 30) as $i) {
            ProviderOutcome::create([
                'provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success',
                'occurred_at' => now()->subDays(60), // outside the 30-day window
            ]);
        }
        $this->outcomes('fivesim', success: 5, failure: 15); // 20 total, in-window, 25% success

        $this->assertSame(0.25, app(NciScorer::class)->publicSuccessRate('fivesim'));
    }

    public function test_it_is_cached_and_survives_new_outcomes_until_flushed(): void
    {
        $this->outcomes('fivesim', success: 18, failure: 2);
        $scorer = app(NciScorer::class);

        $this->assertSame(0.9, $scorer->publicSuccessRate('fivesim'));

        // A new failure lands, but the cached read stays stale until flushed.
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'failure', 'occurred_at' => now()]);
        $this->assertSame(0.9, $scorer->publicSuccessRate('fivesim'));

        NciScorer::flushPublicSuccessRate('fivesim');
        $this->assertNotSame(0.9, $scorer->publicSuccessRate('fivesim'));
    }

    public function test_best_public_success_rate_picks_the_highest_qualifying_lane_member(): void
    {
        $this->outcomes('herosms', success: 12, failure: 8);  // 20 total, 60%
        $this->outcomes('virtsms', success: 4, failure: 1);   // 5 total, below threshold — excluded
        $this->outcomes('fivesim', success: 19, failure: 1);  // 20 total, 95%

        $best = app(NciScorer::class)->bestPublicSuccessRate(['fivesim', 'herosms', 'virtsms']);

        $this->assertSame(0.95, $best);
    }

    public function test_best_public_success_rate_is_null_when_nothing_in_the_lane_qualifies(): void
    {
        $this->outcomes('virtsms', success: 4, failure: 1); // below threshold

        $this->assertNull(app(NciScorer::class)->bestPublicSuccessRate(['virtsms', 'unknown-provider']));
    }

    public function test_the_numbers_country_picker_surfaces_a_qualifying_success_rate(): void
    {
        // Nigeria's OTP lane (non-US) is ['fivesim', 'herosms', 'virtsms'].
        $this->outcomes('fivesim', success: 19, failure: 1); // 95%

        $options = CountryPickerSources::options('numbers');
        $nigeria = collect($options)->firstWhere('code', 'nigeria');

        $this->assertNotNull($nigeria);
        $this->assertSame(0.95, $nigeria['success']);
    }

    public function test_the_numbers_country_picker_shows_no_rate_when_data_is_thin(): void
    {
        $options = CountryPickerSources::options('numbers'); // no outcomes recorded at all
        $nigeria = collect($options)->firstWhere('code', 'nigeria');

        $this->assertNotNull($nigeria);
        $this->assertNull($nigeria['success']);
    }

    // -------------------------- Tier 5 #15: dailySuccessRateTrend() --------------------------

    public function test_daily_success_rate_trend_returns_one_entry_per_day_oldest_first(): void
    {
        $trend = app(NciScorer::class)->dailySuccessRateTrend('fivesim', 7);

        $this->assertCount(7, $trend);
        $this->assertSame(now()->subDays(6)->toDateString(), $trend[0]['date']);
        $this->assertSame(now()->toDateString(), $trend[6]['date']);
    }

    public function test_daily_success_rate_trend_computes_the_real_per_day_success_percentage(): void
    {
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()]);
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()]);
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()]);
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'failure', 'occurred_at' => now()]);

        $trend = app(NciScorer::class)->dailySuccessRateTrend('fivesim', 7);

        $this->assertSame(75.0, $trend[6]['rate_pct']);
    }

    public function test_daily_success_rate_trend_has_a_null_rate_for_a_day_with_no_outcomes(): void
    {
        $trend = app(NciScorer::class)->dailySuccessRateTrend('quibity', 7);

        $this->assertNull($trend[0]['rate_pct']);
        $this->assertNull($trend[6]['rate_pct']);
    }

    public function test_daily_success_rate_trend_ignores_outcomes_outside_the_requested_window(): void
    {
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()->subDays(10)]);

        $trend = app(NciScorer::class)->dailySuccessRateTrend('fivesim', 7);

        $this->assertTrue(collect($trend)->every(fn ($d) => $d['rate_pct'] === null));
    }
}
