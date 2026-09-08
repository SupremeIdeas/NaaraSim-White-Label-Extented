<?php

namespace Tests\Feature;

use App\Models\MerchantEarning;
use App\Models\OrderLog;
use App\Models\Partner;
use App\Models\PartnerEarning;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\SmsOrder;
use App\Models\User;
use App\Services\Partners\PartnerPayoutService;
use App\Services\Partners\PlatformProfitService;
use App\Support\PartnerSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Partner program: platform-wide profit-share with UNCONDITIONAL withdrawal.
 * Verifies the exact profit boundary (net of merchant cuts), the weekly/monthly
 * period calculation, idempotency, and that no merchant-style gate applies.
 */
class PartnerProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PartnerSettings::FLAG, true);
        Setting::setValue('payouts.enabled', true);
    }

    /** created_at is guarded, so backdate a fresh row explicitly. */
    private function at(Model $m, string $date): Model
    {
        $m->forceFill(['created_at' => Carbon::parse($date), 'updated_at' => Carbon::parse($date)])->save();

        return $m;
    }

    private function esimProfit(float $profit, string $date): void
    {
        $this->at(OrderLog::create(['user_id' => User::factory()->create()->id, 'provider' => 'esimgo', 'provider_cost' => 0, 'charged_to_user' => $profit, 'profit' => $profit, 'result' => 'success']), $date);
    }

    private function partner(array $attrs = []): Partner
    {
        $user = User::factory()->create();
        $p = Partner::create(array_merge([
            'owner_user_id' => $user->id,
            'status' => Partner::ACTIVE,
            'profit_share_pct' => 10,
            'payout_cadence' => Partner::CADENCE_MONTHLY,
            'payout_mode' => Partner::MODE_MANUAL,
        ], $attrs));
        $p->forceFill(['created_at' => Carbon::parse('2026-05-10')])->save();

        return $p;
    }

    private function verifiedAccount(Partner $p): void
    {
        PayoutAccount::create(['user_id' => $p->owner_user_id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'USD', 'bank_code' => '058', 'bank_name' => 'GT', 'account_number' => '1', 'account_name' => 'A', 'provider' => 'paystack', 'is_verified' => true]);
    }

    public function test_the_profit_boundary_excludes_merchant_cuts(): void
    {
        $start = Carbon::parse('2026-06-01')->startOfDay();
        $end = Carbon::parse('2026-06-30')->endOfDay();

        $this->esimProfit(3.0, '2026-06-05');
        $this->at(SmsOrder::create(['user_id' => User::factory()->create()->id, 'provider' => 'fivesim', 'service_name' => 'wa', 'country' => 'ng', 'type' => 'otp', 'phone_number' => '+100', 'provider_cost' => 0.5, 'charged_to_user' => 2, 'profit' => 1.5, 'status' => 'completed']), '2026-06-10');
        // A merchant's cut is booked separately and must be netted out of the share.
        $merchant = \App\Models\Merchant::create(['owner_user_id' => User::factory()->create()->id, 'business_name' => 'M', 'slug' => 'm', 'status' => 'active']);
        $this->at(MerchantEarning::create(['merchant_id' => $merchant->id, 'type' => MerchantEarning::ACCRUAL, 'amount' => 1, 'balance_after' => 1, 'currency' => 'USD', 'reference' => 'me1']), '2026-06-11');

        // 3 + 1.5 − 1 = 3.5 (platform retained margin, no merchant double count).
        $this->assertSame(3.5, app(PlatformProfitService::class)->profitForPeriod($start, $end));
    }

    public function test_a_monthly_run_accrues_the_share_and_creates_a_pending_payout(): void
    {
        Carbon::setTestNow('2026-07-05'); // June is a completed month
        $partner = $this->partner(['last_period_end' => '2026-05-31']);
        $this->esimProfit(100.0, '2026-06-15'); // 10% share = 10
        $this->verifiedAccount($partner);

        app(PartnerPayoutService::class)->runPartner($partner->fresh());

        $this->assertSame('10.0000', (string) PartnerEarning::where('partner_id', $partner->id)->where('type', 'accrual')->value('amount'));
        $req = PayoutRequest::where('source_bucket', 'partner_earnings')->first();
        $this->assertNotNull($req);
        $this->assertSame('pending', $req->status); // manual mode → awaits admin approval
        $this->assertSame('10.00', (string) $req->credit_amount);
        Carbon::setTestNow();
    }

    public function test_the_run_is_idempotent_across_reruns(): void
    {
        Carbon::setTestNow('2026-07-05');
        $partner = $this->partner(['last_period_end' => '2026-05-31']);
        $this->esimProfit(50.0, '2026-06-15');

        app(PartnerPayoutService::class)->runPartner($partner->fresh());
        app(PartnerPayoutService::class)->runPartner($partner->fresh());

        $this->assertSame(1, PartnerEarning::where('partner_id', $partner->id)->where('type', 'accrual')->count());
        Carbon::setTestNow();
    }

    public function test_withdrawal_is_unconditional_no_referral_or_spend_gate(): void
    {
        Carbon::setTestNow('2026-07-05');
        // A brand-new partner: zero referrals, zero spend, no enrollment fee.
        $partner = $this->partner(['last_period_end' => '2026-05-31']);
        $this->esimProfit(20.0, '2026-06-15');
        $this->verifiedAccount($partner);

        app(PartnerPayoutService::class)->runPartner($partner->fresh());

        // A payout was created purely because a balance accrued — no other gate.
        $this->assertNotNull(PayoutRequest::where('source_bucket', 'partner_earnings')->first());
        Carbon::setTestNow();
    }

    public function test_the_profit_share_percentage_is_hidden_from_serialisation(): void
    {
        $this->assertArrayNotHasKey('profit_share_pct', $this->partner()->toArray());
    }

    public function test_the_admin_page_is_admin_only_and_promotes_a_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/partners')->assertNotFound();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $target = User::factory()->create(['email' => 'p@ex.com']);

        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\Partners::class)
            ->set('newEmail', 'p@ex.com')->set('newShare', 12)
            ->call('addPartner')->assertHasNoErrors();

        $this->assertDatabaseHas('partners', ['owner_user_id' => $target->id, 'profit_share_pct' => 12, 'status' => 'active']);
    }

    public function test_the_partner_view_404s_for_a_non_partner(): void
    {
        \Livewire\Livewire::actingAs(User::factory()->create())
            ->test(\App\Livewire\PartnerEarnings::class)
            ->assertStatus(404);
    }

    public function test_the_partner_view_shows_dollars_and_never_the_percentage(): void
    {
        $partner = $this->partner(['profit_share_pct' => 37.5]);

        \Livewire\Livewire::actingAs($partner->owner)->test(\App\Livewire\PartnerEarnings::class)
            ->assertOk()
            ->assertSee('Partner earnings')
            ->assertDontSee('37.5'); // the confidential share % is never rendered
    }
}
