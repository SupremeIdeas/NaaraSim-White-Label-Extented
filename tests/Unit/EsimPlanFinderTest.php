<?php

namespace Tests\Unit;

use App\Support\EsimPlanFinder as F;
use Tests\TestCase;

/** The ranking maths of the eSIM Plan Finder: pure functions over lean plan rows (id, mb, days, price). No database. */
class EsimPlanFinderTest extends TestCase
{
    /** @return \Illuminate\Support\Collection<int, array{id:int, mb:?int, days:?int, price:float}> */
    private function rows(): \Illuminate\Support\Collection
    {
        return collect([
            ['id' => 1, 'mb' => 1000, 'days' => 7, 'price' => 4.5],      // sold as "1 GB"
            ['id' => 2, 'mb' => 3072, 'days' => 7, 'price' => 8.0],
            ['id' => 3, 'mb' => 5120, 'days' => 15, 'price' => 12.0],
            ['id' => 4, 'mb' => 20480, 'days' => 30, 'price' => 30.0],
            ['id' => 5, 'mb' => null, 'days' => 7, 'price' => 40.0],     // unlimited
            ['id' => 6, 'mb' => 102400, 'days' => 30, 'price' => 90.0],
            ['id' => 7, 'mb' => 5120, 'days' => 45, 'price' => 20.0],
        ]);
    }

    private function c(array $over = []): array
    {
        return F::sanitise($over + F::defaults());
    }

    public function test_sanitise_never_trusts_the_browser(): void
    {
        $c = F::sanitise(['days' => [7, '7', 'x', 99, '30+', 7], 'need' => '9999', 'min' => '500', 'max' => '2', 'usage' => 'wild', 'trip' => 900, 'sort' => 'DROP TABLE', 'unlimited' => 1]);

        $this->assertSame([7, '30+'], $c['days']);
        $this->assertNull($c['need'], 'unlimited wins over a data amount');
        $this->assertTrue($c['unlimited']);
        $this->assertSame(2.0, $c['min'], 'a reversed price range is swapped, not rejected');
        $this->assertSame(500.0, $c['max']);
        $this->assertSame('medium', $c['usage']);
        $this->assertSame(60, $c['trip']);
        $this->assertSame('best', $c['sort']);

        $big = F::sanitise(['need' => '9999']);
        $this->assertSame(500.0, $big['need']);
        $this->assertSame(F::defaults(), F::sanitise([]));
    }

    public function test_a_1000_mb_plan_counts_as_1_gb_and_big_overbuys_are_dropped(): void
    {
        $ids = F::match($this->rows(), $this->c(['need' => 1]))->pluck('id')->all();

        $this->assertContains(1, $ids, '1000 MB is sold as 1 GB');
        $this->assertContains(2, $ids);
        $this->assertContains(5, $ids, 'unlimited always satisfies a data ask');
        $this->assertContains(3, $ids, '5 GB is inside the 6x overbuy cap for a 1 GB need');
        $this->assertNotContains(4, $ids, '20 GB is more than 6x a 1 GB need');
        $this->assertNotContains(6, $ids);
    }

    public function test_validity_is_any_of_the_chosen_days_and_30_plus_means_longer_than_30(): void
    {
        $this->assertSame([1, 2, 5], F::match($this->rows(), $this->c(['days' => [7]]))->pluck('id')->all());
        $this->assertSame([7], F::match($this->rows(), $this->c(['days' => ['30+']]))->pluck('id')->all());
        $this->assertEqualsCanonicalizing([1, 2, 3, 5], F::match($this->rows(), $this->c(['days' => [7, 15]]))->pluck('id')->all());
    }

    public function test_price_range_and_unlimited_only_filters(): void
    {
        $this->assertSame([1, 2], F::match($this->rows(), $this->c(['min' => 4, 'max' => 9]))->pluck('id')->all());
        $this->assertSame([5], F::match($this->rows(), $this->c(['unlimited' => true]))->pluck('id')->all());
    }

    public function test_best_match_prefers_the_smallest_plan_that_covers_the_need_and_is_cheap(): void
    {
        $c = $this->c(['need' => 3, 'days' => [7]]);
        $ordered = F::order(F::score(F::match($this->rows(), $c), $c), 'best');

        $this->assertSame(2, $ordered->first()['id'], '3 GB for a 3 GB need beats the dearer unlimited plan');
        $this->assertGreaterThan($ordered->last()['score'], $ordered->first()['score']);
    }

    public function test_a_heavy_estimate_lifts_unlimited_but_never_above_a_perfectly_sized_cheaper_plan(): void
    {
        $rows = collect([['id' => 1, 'mb' => 20480, 'days' => 14, 'price' => 30.0], ['id' => 2, 'mb' => null, 'days' => 14, 'price' => 31.0]]);
        $light = $this->c(['need' => 20, 'estimate' => true, 'trip' => 14, 'usage' => 'light']);
        $heavy = $this->c(['need' => 20, 'estimate' => true, 'trip' => 14, 'usage' => 'heavy']);

        $unlimited = fn (array $c) => F::score(F::match($rows, $c), $c)->firstWhere('id', 2)['score'];

        $this->assertGreaterThan($unlimited($light), $unlimited($heavy), 'a heavy estimate scores unlimited higher than a light one');
        $this->assertSame(1, F::order(F::score(F::match($rows, $heavy), $heavy), 'best')->first()['id'], 'a perfectly sized plan that is also cheaper still wins');
    }

    public function test_a_plan_that_outlasts_the_trip_beats_one_that_falls_short(): void
    {
        $rows = collect([['id' => 1, 'mb' => 5120, 'days' => 7, 'price' => 10.0], ['id' => 2, 'mb' => 5120, 'days' => 15, 'price' => 10.0]]);
        $c = $this->c(['need' => 5, 'estimate' => true, 'trip' => 14]);

        $this->assertSame(2, F::order(F::score(F::match($rows, $c), $c), 'best')->first()['id']);
    }

    public function test_plain_sorts_are_predictable(): void
    {
        $scored = F::score(F::match($this->rows(), $this->c()), $this->c());

        $this->assertSame([1, 2, 3, 7, 4, 5, 6], F::order($scored, 'price')->pluck('id')->all());
        $this->assertSame(5, F::order($scored, 'data')->first()['id'], 'unlimited is the most data');
        $this->assertSame(5, F::order($scored, 'pergb')->last()['id'], 'unlimited has no per-GB figure so it sorts last');
        $this->assertSame(7, F::order($scored, 'days')->first()['id']);
    }

    public function test_badges_name_best_cheapest_and_value_once_and_the_why_line_explains(): void
    {
        $c = $this->c(['need' => 5, 'estimate' => true, 'trip' => 14, 'usage' => 'medium']);
        $scored = F::score(F::match($this->rows(), $c), $c);
        $meta = F::annotate($scored, $c);

        $best = F::order($scored, 'best')->first()['id'];
        $this->assertContains('best', $meta[$best]['badges']);
        $cheapest = $scored->sortBy('price')->first()['id'];
        $this->assertContains('cheapest', $meta[$cheapest]['badges']);
        $this->assertCount(1, array_filter($meta, fn ($m) => in_array('best', $m['badges'], true)));
        $this->assertStringContainsString('/GB', $meta[3]['why'] ?? $meta[$best]['why']);
        $this->assertStringContainsString('5 GB', $meta[$best]['why']);
    }

    public function test_nothing_fits_offers_relaxations_with_real_counts(): void
    {
        $c = $this->c(['days' => [1], 'max' => 10]);   // no plan lasts exactly 1 day
        $this->assertCount(0, F::match($this->rows(), $c));

        $keys = collect(F::relaxations($this->rows(), $c))->keyBy('key');
        $this->assertTrue($keys->has('days'), 'dropping the validity brings plans back');
        $this->assertSame(2, $keys['days']['count']);
        $this->assertFalse($keys->has('price'), 'raising the cap alone would still find nothing, so it is not offered');
        $this->assertFalse($keys->has('need'), 'no data amount was chosen, so there is nothing to relax');
        $this->assertSame([], F::relax($c, 'days')['days']);
        $this->assertSame(20.0, F::relax($c, 'price')['max']);
    }

    public function test_active_count_counts_each_kind_of_choice_once(): void
    {
        $this->assertSame(0, F::activeCount($this->c()));
        $this->assertSame(3, F::activeCount($this->c(['days' => [7, 15], 'need' => 5, 'max' => 50])));
    }
}
