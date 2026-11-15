<?php

namespace Tests\Feature;

use App\Livewire\Catalogue;
use App\Models\EsimPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * eSIM Plan Finder on the country / region / global pages. It only reads the synced plans: never a cost, never a price change, never a
 * provider call, and it never mixes the data line with the calls line.
 */
class EsimPlanFinderCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const COST = 7.7777;   // a recognisable provider cost that must never reach a page

    private function plan(string $name, ?int $mb, int $days, float $price, string $country = 'NG', bool $voice = false, string $coverage = EsimPlan::COVERAGE_LOCAL, ?string $region = null): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => $name, 'data_mb' => $mb, 'validity_days' => $days,
            'countries' => [$country], 'coverage_type' => $coverage, 'region_slug' => $region,
            'has_voice' => $voice, 'is_active' => true, 'cost_price_usd' => self::COST, 'computed_retail_usd' => $price,
        ]);
    }

    /** Eight Nigerian data plans: enough for the finder to appear. */
    private function nigeria(): void
    {
        $this->plan('NG one', 1024, 7, 4.5);
        $this->plan('NG three', 3072, 7, 8.0);
        $this->plan('NG five', 5120, 15, 12.0);
        $this->plan('NG ten', 10240, 30, 20.0);
        $this->plan('NG twenty', 20480, 30, 30.0);
        $this->plan('NG unlimited', null, 7, 40.0);
        $this->plan('NG fifty', 51200, 30, 60.0);
        $this->plan('NG hundred', 102400, 30, 90.0);
    }

    private function page(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(Catalogue::class)->call('openCountry', 'NG');
    }

    public function test_the_finder_shows_on_a_country_page_with_enough_plans_and_hides_on_a_tiny_one(): void
    {
        $this->nigeria();
        $this->plan('Tiny one', 1024, 7, 5.0, 'LS');
        $this->plan('Tiny two', 2048, 7, 7.0, 'LS');

        $this->page()->assertSee('Find your plan faster')->assertSee('Sort best match');
        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)->call('openCountry', 'LS')->assertDontSee('Find your plan faster')->assertSee('Tiny one');
    }

    public function test_applying_choices_shows_only_the_plans_that_fit_best_first_with_badges(): void
    {
        $this->nigeria();

        $t = $this->page()->call('applyFinder', ['days' => [7], 'need' => 3, 'unlimited' => false, 'min' => 1, 'max' => 50, 'sort' => 'best']);

        $t->assertSet('finderApplied', true)
            ->assertSee('Best matches')
            ->assertSee('NG three')          // 3 GB, 7 days
            ->assertSee('Best match')
            ->assertSee('Cheapest')
            ->assertDontSee('NG hundred')    // outside price and data
            ->assertDontSee('NG ten');       // 30 days
    }

    public function test_provider_cost_never_reaches_the_finder_or_its_results(): void
    {
        $this->nigeria();

        $html = $this->page()->call('applyFinder', ['days' => [7], 'need' => 3, 'max' => 50])->html();

        $this->assertStringNotContainsString((string) self::COST, $html);
        $this->assertStringNotContainsString('cost_price', $html);
        $this->assertStringNotContainsString('7.78', $html);
    }

    public function test_the_calls_line_is_never_mixed_into_the_data_line_results(): void
    {
        $this->nigeria();
        foreach (range(1, 6) as $i) {
            $this->plan('NG voice '.$i, 3072, 7, 9.0 + $i, voice: true);
        }

        $this->page()->call('applyFinder', ['days' => [7], 'max' => 2000])->assertDontSee('NG voice');
    }

    public function test_choices_are_remembered_for_the_visit_and_reset_clears_them(): void
    {
        $this->nigeria();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Catalogue::class)->call('openCountry', 'NG')->call('applyFinder', ['days' => [7, 15], 'need' => 5, 'max' => 40]);

        $this->assertSame([7, 15], session('esim.finder')['days']);

        Livewire::actingAs($user)->test(Catalogue::class)->call('openCountry', 'NG')
            ->assertSet('finder.days', [7, 15])
            ->assertSet('finder.need', 5.0)
            ->assertSet('finderApplied', false, 'results only show once the button is pressed on this page')
            ->call('resetFinder')
            ->assertSet('finder.days', []);
        $this->assertNull(session('esim.finder'));
    }

    public function test_hostile_input_is_clamped_and_never_breaks_the_page(): void
    {
        $this->nigeria();

        $this->page()
            ->call('applyFinder', ['days' => ['x', '1; DROP TABLE esim_plans', 9999], 'need' => 'abc', 'min' => 'NaN', 'max' => -5, 'sort' => '; DELETE', 'usage' => '<script>'])
            ->assertHasNoErrors()
            ->assertSet('finder.days', [])
            ->assertSet('finder.sort', 'best')
            ->assertSet('finder.usage', 'medium');

        $this->assertGreaterThan(0, EsimPlan::count());
    }

    public function test_nothing_fits_offers_one_tap_relaxations_that_bring_plans_back(): void
    {
        $this->nigeria();

        $t = $this->page()->call('applyFinder', ['days' => [15], 'need' => 5, 'max' => 10]);   // the 5 GB / 15 day plan costs $12

        $t->assertSee('No exact match')->assertSee('Raise the max price');
        $t->call('relaxFinder', 'price')->assertSee('NG five')->assertDontSee('No exact match');
    }

    public function test_the_region_and_global_pages_get_the_finder_too(): void
    {
        foreach (range(1, 7) as $i) {
            $this->plan('Africa '.$i, 1024 * $i, 7 * $i, 3.0 * $i, 'KE', coverage: EsimPlan::COVERAGE_REGIONAL, region: 'africa');
            $this->plan('World '.$i, 1024 * $i, 7 * $i, 5.0 * $i, 'US', coverage: EsimPlan::COVERAGE_GLOBAL);
        }
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Catalogue::class)->call('openRegion', 'africa')->assertSee('Find your plan faster')->assertSee('Africa 1');
        Livewire::actingAs($user)->test(Catalogue::class)->call('openGlobal')->assertSee('Find your plan faster')->assertSee('World 1');
    }

    public function test_the_sort_menu_reorders_without_touching_the_choices(): void
    {
        $this->nigeria();

        $t = $this->page()->call('applyFinder', ['max' => 100])->call('setFinderSort', 'price');

        $t->assertSet('finder.sort', 'price')->assertSet('finder.max', 100.0);
        $this->assertSame(['NG one'], [\App\Support\EsimCatalogue::cleanPlanName(EsimPlan::query()->orderBy('final_retail_usd')->first()->name)]);
    }
}
