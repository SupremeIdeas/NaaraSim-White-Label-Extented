<?php

namespace Tests\Feature;

use App\Events\PayoutReversed;
use App\Livewire\Admin\Payouts;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutTransferResult;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 0 admin controls — the operator toggles payouts and, in manual
 * mode, approves or declines each pending request.
 */
class AdminPayoutsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function fakeEngine(string $sendStatus = 'processing'): void
    {
        $gateway = new class($sendStatus) implements PayoutGatewayInterface
        {
            public function __construct(private string $sendStatus)
            {
            }

            public function name(): string
            {
                return 'paystack';
            }

            public function available(): bool
            {
                return true;
            }

            public function createRecipient(PayoutAccount $account): string
            {
                return 'RCP';
            }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                return new PayoutTransferResult(status: $this->sendStatus, providerRef: 'TRF');
            }

            public function verifyWebhook(\Illuminate\Http\Request $request): bool
            {
                return true;
            }

            public function parseWebhook(\Illuminate\Http\Request $request): ?\App\Services\Payouts\PayoutEvent
            {
                return null;
            }
        };
        $this->app->instance(PayoutService::class, new PayoutService([$gateway]));
    }

    private function pendingRequest(User $user): PayoutRequest
    {
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        return PayoutRequest::create([
            'user_id' => $user->id, 'payout_account_id' => $account->id, 'amount' => 12.0,
            'currency' => 'NGN', 'source_bucket' => 'referral_credits', 'status' => PayoutRequest::PENDING,
            'provider' => 'paystack', 'reference' => 'wd:admin:'.$user->id,
        ]);
    }

    public function test_the_page_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Payouts::class)->assertForbidden();
    }

    public function test_admin_can_toggle_the_feature_and_settings(): void
    {
        Livewire::actingAs($this->superAdmin())->test(Payouts::class)
            ->set('enabled', true)
            ->set('mode', 'auto')
            ->set('minWithdrawal', 20)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(PayoutSettings::enabled());
        $this->assertTrue(PayoutSettings::autopilot());
        $this->assertSame(20.0, PayoutSettings::minWithdrawal());
        $this->assertSame(true, (bool) Setting::getValue(PayoutSettings::FLAG));
    }

    public function test_admin_can_approve_a_pending_payout(): void
    {
        $this->fakeEngine('processing');
        $user = User::factory()->create();
        $req = $this->pendingRequest($user);

        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->set("notes.{$req->id}", 'Checked, looks fine')
            ->call('approve', $req->id);

        $this->assertSame(PayoutRequest::PROCESSING, $req->fresh()->status);
    }

    public function test_admin_can_decline_a_pending_payout_and_reverse_it(): void
    {
        Event::fake([PayoutReversed::class]);
        $this->fakeEngine();
        $user = User::factory()->create();
        $req = $this->pendingRequest($user);

        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->set("notes.{$req->id}", 'Duplicate request')
            ->call('reject', $req->id);

        $this->assertSame(PayoutRequest::REVERSED, $req->fresh()->status);
        Event::assertDispatched(PayoutReversed::class);
    }

    public function test_a_plain_admin_cannot_switch_settlement_to_auto(): void
    {
        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->set('enabled', true)->set('mode', 'auto')->call('save')
            ->assertForbidden();

        $this->assertFalse(PayoutSettings::autopilot());
    }

    public function test_manual_decisions_require_a_note(): void
    {
        $this->fakeEngine('processing');
        $req = $this->pendingRequest(User::factory()->create());

        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->call('approve', $req->id)
            ->assertHasErrors(["notes.{$req->id}"]);
        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->call('reject', $req->id)
            ->assertHasErrors(["notes.{$req->id}"]);

        $this->assertSame(\App\Models\PayoutRequest::PENDING, $req->fresh()->status);
    }

    public function test_an_admin_decision_lands_in_the_decision_log_with_the_note(): void
    {
        $this->fakeEngine('processing');
        $req = $this->pendingRequest(User::factory()->create());

        Livewire::actingAs($admin = $this->admin())->test(Payouts::class)
            ->set("notes.{$req->id}", 'Verified with the user by phone')
            ->call('approve', $req->id);

        $d = \App\Models\PayoutDecision::where('payout_request_id', $req->id)->latest('id')->first();
        $this->assertSame(['approve', 'admin:'.$admin->id], [$d->decision, $d->decided_by]);
        $this->assertSame('Verified with the user by phone', $d->rules[0]['evidence']['note']);
    }

    public function test_any_admin_can_pause_auto_approvals_but_only_a_super_admin_can_save_guardian_settings(): void
    {
        Setting::setValue(PayoutSettings::AUTO_APPROVAL, true);

        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->call('pauseAutoApprovals')
            ->call('saveGuardian')->assertForbidden();
        $this->assertFalse(PayoutSettings::autoApprovalEnabled());

        Livewire::actingAs($this->superAdmin())->test(Payouts::class)
            ->set('autoApproval', true)->set('shadow', false)->set('providerAuto.paystack', true)->set('tierNew', 25)
            ->call('saveGuardian')->assertHasNoErrors();
        $this->assertTrue(PayoutSettings::autoApprovalEnabled());
        $this->assertFalse(PayoutSettings::shadowMode());
        $this->assertTrue(PayoutSettings::providerAutoApprove('paystack'));
        $this->assertFalse(PayoutSettings::providerAutoApprove('stripe'));
        $this->assertSame(25.0, PayoutSettings::tierLimitUsd('new'));
    }

    public function test_trust_override_needs_a_reason_and_is_super_admin_only(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->set('trustEmail', $target->email)->set('trustReason', 'known partner')->call('setTrust')->assertForbidden();

        Livewire::actingAs($this->superAdmin())->test(Payouts::class)
            ->set('trustEmail', $target->email)->set('trustReason', '')->call('setTrust')->assertHasErrors('trustReason');
        Livewire::actingAs($sa = $this->superAdmin())->test(Payouts::class)
            ->set('trustEmail', $target->email)->set('trustTier', 'vip')->set('trustReason', 'known partner')->call('setTrust')->assertHasNoErrors();

        $p = \App\Models\PayoutTrustProfile::find($target->id);
        $this->assertSame(['vip', $sa->id], [$p->tier, $p->override_by]);
    }

    public function test_every_tab_renders_and_the_log_exports(): void
    {
        $this->fakeEngine('processing');
        $this->pendingRequest(User::factory()->create());
        $c = Livewire::actingAs($this->superAdmin())->test(Payouts::class);
        foreach (['queue', 'log', 'guardian', 'trust'] as $tab) {
            $c->set('tab', $tab)->assertOk();
        }
        $c->set('tab', 'log')->call('exportLog')->assertFileDownloaded();
    }

    public function test_user_facing_status_text_never_leaks_rules_and_is_translated(): void
    {
        $r = new \App\Models\PayoutRequest(['status' => 'pending', 'review_state' => 'manual_review', 'hold_reason' => 'name_mismatch', 'provider' => 'paystack']);

        $this->assertSame('extra_check', \App\Support\PayoutStatusText::key($r));
        foreach (['en', 'fr', 'sw', 'ar'] as $lang) {
            app()->setLocale($lang);
            $text = \App\Support\PayoutStatusText::text($r);
            $this->assertNotSame('payouts.status.extra_check', $text, "{$lang} translation missing");
            $this->assertStringNotContainsString('name_mismatch', $text);
        }

        $deferred = new \App\Models\PayoutRequest(['status' => 'pending', 'review_state' => 'deferred', 'hold_reason' => 'float_short']);
        $this->assertSame('queued', \App\Support\PayoutStatusText::key($deferred));
        $this->assertSame('security_hold', \App\Support\PayoutStatusText::key(new \App\Models\PayoutRequest(['status' => 'pending', 'review_state' => 'deferred', 'hold_reason' => 'cooling_off'])));
        $this->assertSame('returned', \App\Support\PayoutStatusText::key(new \App\Models\PayoutRequest(['status' => 'reversed'])));
        $this->assertSame('sent', \App\Support\PayoutStatusText::key(new \App\Models\PayoutRequest(['status' => 'processing'])));
    }

    public function test_the_float_tab_tracks_a_rail_records_a_top_up_and_requires_a_note(): void
    {
        $c = Livewire::actingAs($this->admin())->test(Payouts::class)->set('tab', 'float')
            ->set('floatProvider', 'paystack')->set('floatCurrency', 'ngn')->set('floatAmount', 1000)->set('floatThreshold', 200)
            ->call('trackRail')->assertHasNoErrors();
        $this->assertEquals(1000, \App\Models\PayoutFloatBalance::first()->balance);

        $c->set('floatAmount', 500)->set('floatNote', '')->call('topUp')->assertHasErrors('floatNote');
        $c->set('floatNote', 'bank ref 9')->call('topUp')->assertHasNoErrors();
        $this->assertEquals(1500, \App\Models\PayoutFloatBalance::first()->balance);

        // Signed adjustments are super-admin only.
        $c->set('floatAmount', -50)->set('floatNote', 'bank fee')->call('adjustFloat')->assertForbidden();
        Livewire::actingAs($this->superAdmin())->test(Payouts::class)->set('tab', 'float')
            ->set('floatProvider', 'paystack')->set('floatCurrency', 'NGN')->set('floatAmount', -50)->set('floatNote', 'bank fee')->call('adjustFloat')->assertHasNoErrors();
        $this->assertEquals(1450, \App\Models\PayoutFloatBalance::first()->balance);
    }
}
