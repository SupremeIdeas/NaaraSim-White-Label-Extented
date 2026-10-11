<?php

namespace Tests\Feature;

use App\Livewire\Rewards;
use App\Models\CreditLedger;
use App\Models\User;
use App\Models\UserWallet;
use App\Notifications\RewardClaimedNotification;
use App\Services\Credits\CreditService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Engineering v3 Phase 3.3: a reward claim credits once, tells the member in the toast AND the bell, and a double tap earns nothing extra. */
class RewardClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_claim_credits_once_and_notifies_in_the_bell(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Rewards::class)
            ->call('checkIn')
            ->assertDispatched('nx-toast', variant: 'hero', type: 'success')
            ->assertDispatched('reward-claimed');

        $this->assertSame(1, CreditLedger::where(['user_id' => $user->id, 'source' => 'checkin'])->count());
        Notification::assertSentToTimes($user, RewardClaimedNotification::class, 1);
        Notification::assertSentTo($user, RewardClaimedNotification::class, function ($n) use ($user) {
            $p = $n->inApp($user);

            return $p['icon'] === 'star' && str_contains($p['title'], 'Reward claimed') && str_contains($p['body'], 'NaaraCredits');
        });
    }

    public function test_a_second_tap_earns_nothing_and_does_not_notify_again(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $c = Livewire::actingAs($user)->test(Rewards::class);

        $c->call('checkIn');
        $balance = (float) UserWallet::where('user_id', $user->id)->value('naara_credits');
        $c->call('checkIn')->assertDispatched('nx-toast', type: 'info');
        $c->call('checkIn');

        $this->assertSame(1, CreditLedger::where(['user_id' => $user->id, 'source' => 'checkin'])->count());
        $this->assertSame($balance, (float) UserWallet::where('user_id', $user->id)->value('naara_credits'));
        Notification::assertSentToTimes($user, RewardClaimedNotification::class, 1);
    }

    public function test_the_service_returns_zero_for_a_repeat_so_no_false_earned_message_is_shown(): void
    {
        $user = User::factory()->create();
        $svc = app(CreditService::class);
        $this->assertGreaterThan(0, $svc->checkIn($user));
        $this->assertSame(0.0, $svc->checkIn($user->fresh()));
        $this->assertFalse($svc->canCheckIn($user->fresh()));
    }
}
