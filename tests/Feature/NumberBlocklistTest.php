<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Models\User;
use App\Services\SMS\NumberBlocklist;
use App\Services\SMS\PermanentNumberRouter;
use App\Services\Wallet\WalletService;
use App\Support\ProviderKeys;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/**
 * Recycled-number pre-check (Prompt 11): a permanent number we pulled for
 * abuse/complaint is never re-offered or re-provisioned. Scoped to our own
 * inventory — no cross-provider "clean number" claim is made or tested.
 */
class NumberBlocklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'tok']);
        ProviderKeys::flush();
    }

    public function test_blocking_matches_regardless_of_formatting(): void
    {
        $list = new NumberBlocklist;
        $list->block('+1 (555) 000-1234', reason: 'abuse');

        $this->assertTrue($list->isBlocked('+15550001234'));
        $this->assertTrue($list->isBlocked('15550001234'));
        $this->assertFalse($list->isBlocked('+15550009999'));
    }

    public function test_unblocking_restores_availability(): void
    {
        $list = new NumberBlocklist;
        $list->block('+15550001234');
        $this->assertTrue($list->isBlocked('+15550001234'));

        $list->unblock('+15550001234');
        $this->assertFalse($list->isBlocked('+15550001234'));
    }

    public function test_search_never_offers_a_blocked_number(): void
    {
        (new NumberBlocklist)->block('+15550001234', reason: 'complaint');

        $this->app->instance('number.twilio', new FakePermanentProvider(cost: 1.00, results: [
            ['number' => '+15550001234', 'locality' => 'New York'],   // blocked
            ['number' => '+15550005678', 'locality' => 'New York'],   // clean
        ]));

        $result = app(PermanentNumberRouter::class)->search('usa');
        $numbers = array_column($result['numbers'], 'number');

        $this->assertNotContains('+15550001234', $numbers);
        $this->assertContains('+15550005678', $numbers);
    }

    public function test_provision_refuses_a_blocked_number_without_charging(): void
    {
        (new NumberBlocklist)->block('+15550001234', reason: 'abuse');
        $this->app->instance('number.twilio', new FakePermanentProvider(cost: 1.00));

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        try {
            app(PermanentNumberRouter::class)->provision($user, 'usa', '+15550001234', 'twilio');
            $this->fail('Expected a blocked number to be refused.');
        } catch (SmsException) {
            // expected
        }

        // No charge, no subscription created.
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0, $user->virtualNumbers()->count());
    }

    public function test_the_ops_command_blocks_and_unblocks(): void
    {
        Artisan::call('numbers:block', ['number' => '+15550001234', '--reason' => 'abuse']);
        $this->assertTrue((new NumberBlocklist)->isBlocked('+15550001234'));

        Artisan::call('numbers:block', ['number' => '+15550001234', '--unblock' => true]);
        $this->assertFalse((new NumberBlocklist)->isBlocked('+15550001234'));
    }
}
