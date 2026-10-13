<?php

namespace Tests\Feature;

use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\GiftCardOrder;
use App\Models\ReviewMoment;
use App\Models\SmsOrder;
use App\Models\User;
use App\Support\Reviews\ReviewMoments;
use App\Support\Trustpilot\TrustpilotSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Review moments: asked once, at a calm moment, only after a real success, never filtered by how the person felt. */
class ReviewMomentsTest extends TestCase
{
    use RefreshDatabase;

    private function on(): void
    {
        ReviewMoments::put('enabled', true);
        TrustpilotSettings::put('profile_url', 'https://www.trustpilot.com/review/naara.app');
        ReviewMoments::put('esim_first_purchase.delay', 0);
        ReviewMoments::put('sms_verification.delay', 0);
        ReviewMoments::put('giftcard_purchase.delay', 0);
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    }

    private function esim(User $u, array $over = []): EsimOrder
    {
        $p = EsimPlan::query()->forceCreate(['provider' => 'esimgo', 'provider_plan_id' => 'p'.uniqid(), 'name' => 'Plan', 'type' => 'data', 'coverage_type' => 'local', 'countries' => ['GH'], 'data_mb' => 1024, 'validity_days' => 7, 'cost_price_usd' => 1, 'is_active' => true]);

        return EsimOrder::create($over + ['user_id' => $u->id, 'plan_id' => $p->id, 'provider' => 'esimgo', 'status' => 'processing', 'lpa_string' => 'LPA:1$x$y']);
    }

    public function test_a_delivered_esim_records_a_moment_but_nothing_is_asked_while_it_is_off(): void
    {
        $u = $this->user();
        $o = $this->esim($u);
        $this->assertSame(1, ReviewMoment::query()->where('user_id', $u->id)->where('moment', 'esim_first_purchase')->count());
        $this->assertNull(ReviewMoments::next($u));      // off by default
        $this->actingAs($u)->getJson('/reviews/next')->assertOk()->assertJson(['show' => false]);
    }

    public function test_it_needs_the_switch_and_a_trustpilot_profile(): void
    {
        $u = $this->user();
        $this->esim($u);
        ReviewMoments::put('esim_first_purchase.delay', 0);
        ReviewMoments::put('enabled', true);
        TrustpilotSettings::put('profile_url', 'https://evil.example/x');   // not a Trustpilot address -> no link to send anyone to
        $this->assertFalse(ReviewMoments::enabled());
        $this->assertNull(ReviewMoments::next($u));
        $this->on();
        $this->assertSame('esim_first_purchase', ReviewMoments::next($u)['moment']);
    }

    public function test_the_first_esim_only_and_an_order_without_a_code_does_not_count(): void
    {
        $u = $this->user();
        $this->esim($u, ['lpa_string' => null, 'qr_code_url' => null]);
        $this->assertSame(0, ReviewMoment::query()->count());
        $this->esim($u);
        $this->esim($u);   // a second eSIM is not the "first purchase"
        $this->assertSame(1, ReviewMoment::query()->where('moment', 'esim_first_purchase')->count());
    }

    public function test_a_refunded_order_is_not_asked_about(): void
    {
        $this->on();
        $u = $this->user();
        $o = $this->esim($u);
        $o->forceFill(['status' => 'refunded'])->save();
        $this->assertNull(ReviewMoments::next($u));
    }

    public function test_the_delay_the_window_and_the_cooldown_are_respected_and_each_moment_is_asked_once(): void
    {
        $this->on();
        ReviewMoments::put('esim_first_purchase.delay', 30);
        $u = $this->user();
        $this->esim($u);
        $this->assertNull(ReviewMoments::next($u));                                   // too soon after success
        ReviewMoment::query()->update(['qualified_at' => now()->subMinutes(45)]);
        $this->assertNotNull(ReviewMoments::next($u));                                // now it is time
        $this->assertNull(ReviewMoments::next($u));                                   // and it is asked once

        $v = $this->user();
        $this->esim($v);
        ReviewMoment::query()->where('user_id', $v->id)->update(['qualified_at' => now()->subDays(40)]);
        $this->assertNull(ReviewMoments::next($v));                                   // too old to ask about
    }

    public function test_only_one_ask_at_a_time_across_products(): void
    {
        $this->on();
        $u = $this->user();
        $this->esim($u);
        SmsOrder::query()->forceCreate(['user_id' => $u->id, 'provider' => 'getatext', 'status' => 'pending', 'phone_number' => '+1555']);
        SmsOrder::query()->update(['status' => 'completed']);
        foreach (SmsOrder::all() as $s) { $s->forceFill(['status' => 'pending'])->saveQuietly(); $s->forceFill(['status' => 'completed'])->save(); }
        $this->assertSame(2, ReviewMoment::query()->where('user_id', $u->id)->count());
        $this->assertNotNull(ReviewMoments::next($u));
        $this->assertNull(ReviewMoments::next($u));      // the other waits out the cool-down
    }

    public function test_every_rating_gets_the_same_trustpilot_invitation_and_low_ones_add_support(): void
    {
        $this->on();
        foreach ([1, 3, 5] as $stars) {
            $u = $this->user();
            $this->esim($u);
            $m = ReviewMoments::next($u);
            $r = $this->actingAs($u)->postJson("/reviews/{$m['id']}/rate", ['stars' => $stars])->assertOk();
            $r->assertJsonPath('review_url', 'https://www.trustpilot.com/evaluate/naara.app');
            $stars <= 3 ? $r->assertJsonPath('support_url', route('support')) : $r->assertJsonPath('support_url', null);
            $this->assertSame($stars, ReviewMoment::query()->find($m['id'])->stars);
        }
    }

    public function test_dismiss_opened_validation_and_ownership(): void
    {
        $this->on();
        $u = $this->user();
        $this->esim($u);
        $m = ReviewMoments::next($u);
        foreach ([0, 6, 'x'] as $bad) {
            $this->actingAs($u)->postJson("/reviews/{$m['id']}/rate", ['stars' => $bad])->assertStatus(422);
        }
        $this->actingAs($u)->postJson("/reviews/{$m['id']}/opened")->assertOk();
        $this->assertTrue(ReviewMoment::query()->find($m['id'])->trustpilot_opened);
        $this->actingAs($u)->postJson("/reviews/{$m['id']}/dismiss")->assertOk();
        $this->assertSame('dismissed', ReviewMoment::query()->find($m['id'])->outcome);

        $other = $this->user();
        $this->actingAs($other)->postJson("/reviews/{$m['id']}/rate", ['stars' => 5])->assertNotFound();
        $this->actingAs($other)->postJson("/reviews/{$m['id']}/dismiss")->assertNotFound();
    }

    public function test_a_gift_card_counts_only_when_delivered_and_the_sheet_mounts_only_when_live(): void
    {
        $u = $this->user();
        $o = GiftCardOrder::query()->forceCreate(['user_id' => $u->id, 'provider' => 'x', 'provider_product_id' => '1', 'brand_name' => 'Brand', 'face_value' => 10, 'currency' => 'USD', 'price_charged' => 10, 'transaction_ref' => 'tx'.uniqid(), 'status' => 'processing']);
        $this->assertSame(0, ReviewMoment::query()->count());
        $o->forceFill(['status' => 'delivered'])->save();
        $this->assertSame(1, ReviewMoment::query()->where('moment', 'giftcard_purchase')->count());

        $this->actingAs($u)->get('/dashboard')->assertOk()->assertDontSee('data-review-moment', false);
        $this->on();
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('data-review-moment', false);
    }

    public function test_the_admin_panel_saves_the_rules(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $a = $this->user();
        $a->assignRole('super_admin');
        \Livewire\Livewire::actingAs($a)->test(\App\Livewire\Admin\ReviewMomentsPanel::class)->set('enabled', true)->set('idleMinutes', 20)->set('cooldownDays', 30)->set('on.sms_verification', false)->set('delay.esim_first_purchase', 90)->call('save')->assertHasNoErrors();
        $this->assertTrue(ReviewMoments::switchedOn());
        $this->assertSame(20, ReviewMoments::idleMinutes());
        $this->assertSame(30, ReviewMoments::cooldownDays());
        $this->assertFalse(ReviewMoments::momentOn('sms_verification'));
        $this->assertSame(90, ReviewMoments::delayMinutes('esim_first_purchase'));
        $this->actingAs($this->user())->get(route('admin.review-moments'))->assertNotFound();
    }
}
