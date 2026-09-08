<?php

namespace Tests\Feature;

use App\Livewire\DeveloperPortal;
use App\Models\ApiClient;
use App\Models\Setting;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Developer portal (ROADMAP §Layer 2): self-service keys + money-safe top-up
 * from the user's wallet into the client's prepaid API balance.
 */
class DeveloperPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('developer_api.enabled', true, 'developer_api');
    }

    public function test_the_portal_is_hidden_when_the_api_is_disabled(): void
    {
        Setting::setValue('developer_api.enabled', false, 'developer_api');
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $this->actingAs($user)->get('/developer')->assertNotFound();
    }

    public function test_creating_a_key_reveals_the_plaintext_once_and_lists_the_client(): void
    {
        $user = User::factory()->create();

        $comp = Livewire::actingAs($user)->test(DeveloperPortal::class)
            ->set('name', 'My app')
            ->set('scopes', ['catalogue', 'order'])
            ->call('create')
            ->assertSet('plainToken', fn ($t) => is_string($t) && str_contains($t, '|'));

        $client = ApiClient::where('owner_user_id', $user->id)->firstOrFail();
        $this->assertSame(['catalogue', 'order'], $client->scopes);

        // Dismissing hides the one-time reveal.
        $comp->call('hideToken')->assertSet('plainToken', null);
    }

    public function test_topping_up_moves_money_from_wallet_to_the_api_balance(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 50, 'USD');
        $client = ApiClient::create([
            'owner_user_id' => $user->id, 'name' => 'app', 'scopes' => ['order'], 'is_active' => true,
        ]);

        Livewire::actingAs($user)->test(DeveloperPortal::class)
            ->set("topUp.{$client->id}", '15')
            ->call('fund', $client->id);

        $this->assertSame('15.0000', (string) $client->fresh()->prepaid_balance_usd);
        $this->assertSame('35.0000', (string) $user->wallet->fresh()->usd_balance); // 50 - 15
    }

    public function test_topping_up_more_than_the_wallet_holds_charges_nothing(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 5, 'USD');
        $client = ApiClient::create([
            'owner_user_id' => $user->id, 'name' => 'app', 'scopes' => ['order'], 'is_active' => true,
        ]);

        Livewire::actingAs($user)->test(DeveloperPortal::class)
            ->set("topUp.{$client->id}", '20')
            ->call('fund', $client->id)
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'too low'));

        $this->assertSame('0.0000', (string) $client->fresh()->prepaid_balance_usd);
        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance); // untouched
    }

    public function test_a_user_cannot_touch_another_users_key(): void
    {
        $mine = User::factory()->create();
        $theirs = ApiClient::create([
            'owner_user_id' => User::factory()->create()->id, 'name' => 'not mine',
            'scopes' => ['order'], 'is_active' => true, 'prepaid_balance_usd' => 0,
        ]);
        app(WalletService::class)->credit($mine, 50, 'USD');

        Livewire::actingAs($mine)->test(DeveloperPortal::class)
            ->set("topUp.{$theirs->id}", '10')
            ->call('fund', $theirs->id);

        // Nothing moved — the target isn't the acting user's client.
        $this->assertSame('0.0000', (string) $theirs->fresh()->prepaid_balance_usd);
        $this->assertSame('50.0000', (string) $mine->wallet->fresh()->usd_balance);
    }

    public function test_revoking_a_key_disables_it_and_kills_its_token(): void
    {
        $user = User::factory()->create();
        $comp = Livewire::actingAs($user)->test(DeveloperPortal::class)
            ->set('name', 'app')->call('create');
        $token = $comp->get('plainToken');
        $client = ApiClient::where('owner_user_id', $user->id)->firstOrFail();

        $comp->call('revoke', $client->id);

        $this->assertFalse($client->fresh()->is_active);
        $this->assertNull(PersonalAccessToken::findToken($token));
    }
}
