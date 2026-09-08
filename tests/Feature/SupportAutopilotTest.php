<?php

namespace Tests\Feature;

use App\Jobs\PollSmsOtpJob;
use App\Models\CreditLedger;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\SmsOrder;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\SupportTools;
use App\Support\CreditSettings;
use App\Support\SupportAutopilot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * NaaraCare autopilot resolution (owner request). The AI may resolve a bounded
 * allowlist of safe tickets itself; everything critical stays with staff. These
 * tests prove the guardrails: the money lever is capped and idempotent, the
 * non-financial actions work, and the master switch disables all of it.
 */
class SupportAutopilotTest extends TestCase
{
    use RefreshDatabase;

    private function conv(User $user): SupportConversation
    {
        return SupportConversation::create(['user_id' => $user->id, 'title' => 't', 'status' => 'open']);
    }

    private function enableAutopilot(float $goodwillCapUsd = 0): void
    {
        \App\Models\Setting::setValue('support.autopilot.enabled', true, 'support');
        \App\Models\Setting::setValue('support.autopilot.goodwill_cap_usd', $goodwillCapUsd, 'support');
        SupportAutopilot::flush();
    }

    public function test_refresh_number_code_repolls_a_pending_order(): void
    {
        Queue::fake();
        RateLimiter::clear('autopilot:repoll:1');
        $this->enableAutopilot();
        $user = User::factory()->create();
        $conv = $this->conv($user);
        $order = SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'service_name' => 'whatsapp', 'status' => 'waiting', 'phone_number' => '+123']);

        $out = (new SupportTools($user, $conv))->execute('refresh_number_code', ['order_id' => $order->id]);

        $this->assertTrue($out['done']);
        Queue::assertPushed(PollSmsOtpJob::class, fn ($j) => $j->smsOrderId === $order->id);
        $this->assertNotEmpty($conv->fresh()->autopilot_log);
    }

    public function test_refresh_number_code_cannot_touch_another_users_order(): void
    {
        Queue::fake();
        $this->enableAutopilot();
        $me = User::factory()->create();
        $victim = User::factory()->create();
        $order = SmsOrder::create(['user_id' => $victim->id, 'provider' => 'fivesim', 'service_name' => 'x', 'status' => 'waiting', 'phone_number' => '+1']);

        $out = (new SupportTools($me, $this->conv($me)))->execute('refresh_number_code', ['order_id' => $order->id]);

        $this->assertSame('not_found', $out['error']);
        Queue::assertNothingPushed();
    }

    public function test_resend_esim_setup_emails_the_owner(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        \App\Support\MailSettings::flush();
        $this->enableAutopilot();
        $user = User::factory()->create();
        $plan = EsimPlan::create(['provider' => 'esimgo', 'provider_plan_id' => 'p', 'name' => 'USA 3GB', 'cost_price_usd' => 3, 'computed_retail_usd' => 9]);
        $order = EsimOrder::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => 'processing', 'price_charged' => 9, 'wholesale_cost' => 3]);

        $out = (new SupportTools($user, $this->conv($user)))->execute('resend_esim_setup', ['order_id' => $order->id]);

        $this->assertTrue($out['done']);
        Notification::assertSentTo($user, \App\Notifications\OrderPlacedNotification::class);
    }

    public function test_goodwill_is_disabled_when_the_cap_is_zero(): void
    {
        $this->enableAutopilot(goodwillCapUsd: 0); // off
        $user = User::factory()->create();

        $out = (new SupportTools($user, $this->conv($user)))->execute('grant_goodwill_credit', ['amount_usd' => 1, 'reason' => 'x']);

        $this->assertFalse($out['done']);
        $this->assertSame(0, CreditLedger::where('user_id', $user->id)->count());
    }

    public function test_goodwill_over_the_cap_is_refused(): void
    {
        $this->enableAutopilot(goodwillCapUsd: 2.00);
        $user = User::factory()->create();

        $out = (new SupportTools($user, $this->conv($user)))->execute('grant_goodwill_credit', ['amount_usd' => 5, 'reason' => 'too much']);

        $this->assertFalse($out['done']);
        $this->assertSame(0, CreditLedger::where('user_id', $user->id)->count());
    }

    public function test_goodwill_within_cap_grants_once_per_ticket(): void
    {
        CreditSettings::flush();
        \App\Models\Setting::setValue('credits.enabled', true, 'credits');
        \App\Models\Setting::setValue('credits.per_usd', 100, 'credits');
        CreditSettings::flush();
        $this->enableAutopilot(goodwillCapUsd: 2.00);
        $user = User::factory()->create();
        $conv = $this->conv($user);
        $tools = new SupportTools($user, $conv);

        $first = $tools->execute('grant_goodwill_credit', ['amount_usd' => 1.50, 'reason' => 'late code']);
        $this->assertTrue($first['done']);
        $this->assertSame(150.0, (float) $first['credits_granted']); // 1.50 * 100 per_usd

        // A second attempt on the SAME ticket is idempotent — no double grant.
        $second = $tools->execute('grant_goodwill_credit', ['amount_usd' => 1.50, 'reason' => 'again']);
        $this->assertFalse($second['done']);
        $this->assertSame(1, CreditLedger::where('user_id', $user->id)->where('source', 'goodwill')->count());
    }

    public function test_goodwill_cannot_be_farmed_across_multiple_tickets(): void
    {
        CreditSettings::flush();
        \App\Models\Setting::setValue('credits.enabled', true, 'credits');
        \App\Models\Setting::setValue('credits.per_usd', 100, 'credits');
        CreditSettings::flush();
        $this->enableAutopilot(goodwillCapUsd: 2.00);
        $user = User::factory()->create();

        // First ticket: goodwill applies.
        $first = (new SupportTools($user, $this->conv($user)))
            ->execute('grant_goodwill_credit', ['amount_usd' => 1, 'reason' => 'a']);
        $this->assertTrue($first['done']);

        // A brand-new ticket for the SAME user is refused (7-day per-user cap).
        $second = (new SupportTools($user, $this->conv($user)))
            ->execute('grant_goodwill_credit', ['amount_usd' => 1, 'reason' => 'b']);
        $this->assertFalse($second['done']);
        $this->assertSame(1, CreditLedger::where('user_id', $user->id)->where('source', 'goodwill')->count());
    }

    public function test_resolve_ticket_marks_it_resolved(): void
    {
        $this->enableAutopilot();
        $user = User::factory()->create();
        $conv = $this->conv($user);

        $out = (new SupportTools($user, $conv))->execute('resolve_ticket', ['summary' => 'fixed it']);

        $this->assertTrue($out['done']);
        $this->assertSame('resolved', $conv->fresh()->status);
    }

    public function test_the_master_switch_disables_every_autopilot_action(): void
    {
        Queue::fake();
        \App\Models\Setting::setValue('support.autopilot.enabled', false, 'support');
        \App\Models\Setting::setValue('support.autopilot.goodwill_cap_usd', 5, 'support');
        SupportAutopilot::flush();
        $user = User::factory()->create();
        $conv = $this->conv($user);
        $order = SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'service_name' => 'x', 'status' => 'waiting', 'phone_number' => '+1']);
        $tools = new SupportTools($user, $conv);

        $this->assertFalse($tools->execute('refresh_number_code', ['order_id' => $order->id])['done']);
        $this->assertFalse($tools->execute('grant_goodwill_credit', ['amount_usd' => 1, 'reason' => 'x'])['done']);
        $this->assertFalse($tools->execute('resolve_ticket', ['summary' => 'x'])['done']);
        Queue::assertNothingPushed();
        $this->assertSame(0, CreditLedger::where('user_id', $user->id)->count());
    }
}
