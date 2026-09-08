<?php

namespace Tests\Feature;

use App\Livewire\Admin\EsimControlCenter;
use App\Livewire\Catalogue;
use App\Models\EsimCountryImage;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Support\CountryNames;
use App\Support\EsimCatalogue;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Popular Destinations" photo-card row on the eSIM Trending tab (owner
 * request). One shared layout across every theme. A destination shows when it
 * is EITHER actually bought (eSIM orders on the active line) OR admin-featured
 * (is_featured), ranked by purchase volume first and the featured flag as the
 * default when there are no sales yet. When NEITHER exists anywhere in the
 * catalogue (a brand-new install, before the first sale or the first admin
 * featured-toggle) every country with an active local plan becomes eligible,
 * cheapest-first — the row is never silently empty on day one. Its photo is
 * the pre-existing admin-editable per-country image
 * (EsimCountryImage.detail_image_path, set in Admin → eSIM Control Center) —
 * no new admin uploader.
 */
class EsimPopularDestinationsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $overrides = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'has_voice' => false, 'is_active' => true, 'is_featured' => true,
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 1024, 'validity_days' => 7,
            'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ], $overrides));
    }

    private function order(EsimPlan $plan, string $status = 'active'): EsimOrder
    {
        return EsimOrder::create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $plan->id,
            'provider' => 'esimgo',
            'status' => $status,
            'price_charged' => 9,
            'wholesale_cost' => 3,
            'currency' => 'USD',
        ]);
    }

    public function test_a_country_with_a_featured_local_plan_shows_as_a_popular_destination(): void
    {
        $this->plan();

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('Popular Destinations')
            ->assertSee(CountryNames::name('FR'))
            ->assertSee("openCountry('FR')", false);
    }

    public function test_a_country_with_no_featured_plan_still_shows_as_the_day_one_fallback(): void
    {
        // Zero purchases anywhere AND nothing admin-featured yet (a brand-new
        // install) must still show something — the row is never silently
        // empty just because no admin has visited the featured toggle yet.
        $this->plan(['is_featured' => false]);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('Popular Destinations')
            ->assertSee(CountryNames::name('FR'));
    }

    public function test_an_unfeatured_unbought_country_is_excluded_once_another_has_real_signal(): void
    {
        // FR is featured (real signal exists elsewhere), so the day-one
        // fallback no longer applies — NG (neither bought nor featured) drops
        // out of the row exactly as before.
        $this->plan(['countries' => ['FR'], 'is_featured' => true]);
        $this->plan(['countries' => ['NG'], 'is_featured' => false]);
        EsimCatalogue::flush();

        $codes = collect(EsimCatalogue::popularDestinations(false))->pluck('code');
        $this->assertTrue($codes->contains('FR'));
        $this->assertFalse($codes->contains('NG'));
    }

    public function test_a_multi_country_or_global_featured_plan_is_excluded(): void
    {
        // Regional (2+ countries) — the Regions tab covers this, not a
        // single-destination card.
        $this->plan(['coverage_type' => EsimPlan::COVERAGE_REGIONAL, 'countries' => ['FR', 'DE'], 'region_slug' => 'europe']);
        // Global — no single destination to show a card for.
        $this->plan(['coverage_type' => EsimPlan::COVERAGE_GLOBAL, 'countries' => []]);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertDontSee('Popular Destinations');
    }

    public function test_the_photo_is_the_existing_admin_editable_country_image(): void
    {
        EsimCountryImage::create(['country_code' => 'FR', 'detail_image_path' => 'https://cdn.test/fr-photo.webp']);
        $this->plan();

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('https://cdn.test/fr-photo.webp', false);
    }

    public function test_a_country_with_no_photo_still_renders_a_clean_card(): void
    {
        // No EsimCountryImage row at all for FR — photo is optional, never blocking.
        $this->plan();

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertOk()
            ->assertSee(CountryNames::name('FR'));
    }

    public function test_the_teaser_shows_the_cheapest_featured_plans_price_and_data(): void
    {
        $this->plan(['computed_retail_usd' => 9, 'data_mb' => 1024]);
        $this->plan(['provider_plan_id' => 'p-'.uniqid(), 'computed_retail_usd' => 4, 'data_mb' => 512]);

        $rows = EsimCatalogue::popularDestinations(false);
        $fr = collect($rows)->firstWhere('code', 'FR');

        $this->assertNotNull($fr);
        $this->assertSame(4.0, $fr['from_usd']); // the cheaper of the two
        $this->assertSame(512, $fr['data_mb']);
        $this->assertSame(2, $fr['count']);
    }

    public function test_toggling_featured_off_removes_the_destination_without_a_manual_cache_flush(): void
    {
        $this->seed(RoleSeeder::class);
        // A second, always-featured country (GB) keeps real signal in the
        // catalogue throughout, so toggling FR off tests FR's own exclusion
        // rather than tripping the day-one empty-catalogue fallback.
        $this->plan(['countries' => ['GB']]);
        $plan = $this->plan();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('Popular Destinations')
            ->assertSee("openCountry('FR')", false);

        Livewire::actingAs($admin)->test(EsimControlCenter::class)->call('togglePopular', $plan->id);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('Popular Destinations')
            ->assertDontSee("openCountry('FR')", false);
    }

    public function test_the_full_line_and_data_line_are_scoped_independently(): void
    {
        $this->plan(['has_voice' => false]); // FR on the data-only line

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSet('tab', 'data')
            ->assertSee('Popular Destinations')
            ->call('setTab', 'full')
            ->assertDontSee('Popular Destinations');
    }

    // ---- Volume ranking + no-record fallback (owner request) ---------------

    public function test_a_bought_country_shows_even_without_the_featured_flag(): void
    {
        // NG has a plan that is NOT featured, but it's been purchased — it must
        // still appear (owner: "most bought countries by volume").
        $ng = $this->plan(['countries' => ['NG'], 'is_featured' => false]);
        $this->order($ng);
        EsimCatalogue::flush();

        $codes = collect(EsimCatalogue::popularDestinations(false))->pluck('code');
        $this->assertTrue($codes->contains('NG'), 'A purchased country should appear even when not featured.');
    }

    public function test_a_failed_or_cancelled_order_does_not_count_as_bought(): void
    {
        // FR keeps real signal (featured) in the catalogue throughout, so
        // NG's failed/cancelled orders are tested in isolation from the
        // day-one empty-catalogue fallback.
        $this->plan(['countries' => ['FR'], 'is_featured' => true]);
        $ng = $this->plan(['countries' => ['NG'], 'is_featured' => false]);
        $this->order($ng, 'failed');
        $this->order($ng, 'cancelled');
        EsimCatalogue::flush();

        // Not featured + no real purchase → excluded.
        $codes = collect(EsimCatalogue::popularDestinations(false))->pluck('code');
        $this->assertFalse($codes->contains('NG'));
    }

    public function test_more_bought_countries_rank_ahead_of_featured_only_ones(): void
    {
        // FR: featured, zero sales. NG: not featured, two sales. JP: not
        // featured, one sale. Expected order by volume: NG, JP, then FR.
        $this->plan(['countries' => ['FR'], 'is_featured' => true]);
        $ng = $this->plan(['countries' => ['NG'], 'is_featured' => false]);
        $jp = $this->plan(['countries' => ['JP'], 'is_featured' => false]);
        $this->order($ng);
        $this->order($ng);
        $this->order($jp);
        EsimCatalogue::flush();

        $codes = collect(EsimCatalogue::popularDestinations(false))->pluck('code')->all();
        $this->assertSame(['NG', 'JP', 'FR'], array_slice($codes, 0, 3));
    }

    public function test_with_no_sales_it_falls_back_to_featured_countries(): void
    {
        // Zero orders anywhere → the row is the curated featured set.
        $this->plan(['countries' => ['FR'], 'is_featured' => true]);
        $this->plan(['countries' => ['GB'], 'is_featured' => true]);
        EsimCatalogue::flush();

        $codes = collect(EsimCatalogue::popularDestinations(false))->pluck('code');
        $this->assertTrue($codes->contains('FR'));
        $this->assertTrue($codes->contains('GB'));
    }

    public function test_with_no_sales_and_nothing_featured_it_falls_back_to_every_country_cheapest_first(): void
    {
        // Day-one state (owner's real report): a brand-new catalogue with zero
        // orders AND zero admin-featured plans must still populate the row,
        // ranked cheapest-first — not sit empty until the first sale or the
        // first admin visits the featured toggle.
        $this->plan(['countries' => ['FR'], 'is_featured' => false, 'computed_retail_usd' => 9]);
        $this->plan(['countries' => ['GB'], 'is_featured' => false, 'computed_retail_usd' => 3]);
        EsimCatalogue::flush();

        $codes = collect(EsimCatalogue::popularDestinations(false))->pluck('code')->all();
        $this->assertSame(['GB', 'FR'], $codes);
    }
}
