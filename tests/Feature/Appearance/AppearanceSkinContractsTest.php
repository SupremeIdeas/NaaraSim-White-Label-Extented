<?php

namespace Tests\Feature\Appearance;

use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Support\Appearance\ActiveCountryResolver;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Appearance\UpdateUserAppearance;
use Illuminate\Support\Facades\Cache;

/** Prompt 20 §44 (Passport country) and §45 (Golden Hour time of day): data contracts, rendered only for their own skin. */
class AppearanceSkinContractsTest extends AppearanceTestCase
{
    private function plan(array $countries): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p'.uniqid(), 'name' => 'Plan', 'type' => 'data', 'coverage_type' => count($countries) === 1 ? 'local' : 'regional',
            'data_mb' => 1024, 'validity_days' => 7, 'countries' => $countries, 'cost_price_usd' => 1, 'final_retail_usd' => 2, 'is_active' => true,
        ]);
    }

    private function esim($user, EsimPlan $plan, string $status = 'active', ?string $at = null): EsimOrder
    {
        return EsimOrder::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => $status, 'activated_at' => $at ?? now(), 'price_charged' => 2, 'wholesale_cost' => 1, 'currency' => 'USD']);
    }

    public function test_the_country_is_the_most_recent_active_single_country_esim_then_profile_then_default(): void
    {
        $u = $this->member(['country_code' => 'GH']);
        $this->assertSame('gh', ActiveCountryResolver::for($u), 'no eSIM: the profile country');

        Cache::flush();
        $this->esim($u, $this->plan(['JP']), 'active', now()->subDays(5)->toDateTimeString());
        $this->esim($u, $this->plan(['BE']), 'active', now()->subDay()->toDateTimeString());
        $this->esim($u, $this->plan(['AU']), 'expired', now()->toDateTimeString());
        $this->esim($u, $this->plan(['FR', 'DE', 'ES']), 'active', now()->toDateTimeString());   // regional: no single country to show
        $this->assertSame('be', ActiveCountryResolver::for($u), 'the newest ACTIVE single-country eSIM wins');

        $nobody = $this->member(['country_code' => null]);
        $this->assertSame('ng', ActiveCountryResolver::for($nobody), 'platform default');
        $this->assertSame('ng', ActiveCountryResolver::for(null));
    }

    public function test_a_garbage_country_never_reaches_the_page(): void
    {
        $u = $this->member(['country_code' => '"><script>']);
        $this->assertSame('ng', ActiveCountryResolver::for($u));
    }

    public function test_time_of_day_follows_the_members_timezone_with_the_documented_boundaries(): void
    {
        $lagos = $this->member(['timezone' => 'Africa/Lagos']);   // UTC+1
        $at = fn (string $utc) => AppearanceResolver::timeOfDay($lagos, new \DateTimeImmutable($utc, new \DateTimeZone('UTC')));

        $this->assertSame('night', $at('2026-01-01 03:59'));   // 04:59 local
        $this->assertSame('dawn', $at('2026-01-01 04:00'));    // 05:00 local
        $this->assertSame('dawn', $at('2026-01-01 06:59'));    // 07:59 local
        $this->assertSame('day', $at('2026-01-01 07:00'));
        $this->assertSame('day', $at('2026-01-01 15:59'));     // 16:59 local
        $this->assertSame('dusk', $at('2026-01-01 16:00'));
        $this->assertSame('dusk', $at('2026-01-01 18:59'));    // 19:59 local
        $this->assertSame('night', $at('2026-01-01 19:00'));
        $this->assertSame('day', AppearanceResolver::timeOfDay($this->member(['timezone' => null])), 'unknown timezone: day');
        $this->assertSame('day', AppearanceResolver::timeOfDay($this->member(['timezone' => 'Mars/Olympus'])), 'a bad timezone never throws');
    }

    public function test_the_attributes_render_only_for_their_own_skin(): void
    {
        $this->builtSkins(['passport', 'golden', 'neo']);
        $u = $this->member(['country_code' => 'JP', 'timezone' => 'Africa/Lagos']);

        $html = fn () => preg_match('/<html[^>]*>/', $this->actingAs($u)->get(route('account.appearance'))->getContent(), $m) ? $m[0] : '';

        (new UpdateUserAppearance)($u, ['skin' => 'passport']);
        $this->assertStringContainsString('data-nx-country="jp"', $html());
        $this->assertStringNotContainsString('data-nx-tod', $html());

        (new UpdateUserAppearance)($u, ['skin' => 'golden']);
        $this->assertMatchesRegularExpression('/data-nx-tod="(dawn|day|dusk|night)"/', $html());
        $this->assertStringNotContainsString('data-nx-country', $html());

        (new UpdateUserAppearance)($u, ['skin' => 'neo']);
        $this->assertStringNotContainsString('data-nx-country', $html());
        $this->assertStringNotContainsString('data-nx-tod', $html());
    }
}
