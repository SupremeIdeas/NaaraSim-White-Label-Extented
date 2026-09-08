<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\User;
use App\Services\Api\ApiClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Developer API clients + scoped Sanctum keys (ROADMAP §Layer 2). The client is
 * the tokenable; scopes become token abilities; the plaintext key is shown once.
 */
class ApiClientTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ApiClientService
    {
        return app(ApiClientService::class);
    }

    public function test_creating_a_client_mints_a_scoped_token_bound_to_the_client(): void
    {
        $dev = User::factory()->create();
        ['client' => $client, 'token' => $token] = $this->service()->create($dev, 'My app', ['catalogue', 'order']);

        $this->assertSame($dev->id, $client->owner_user_id);
        $this->assertSame(['catalogue', 'order'], $client->scopes);
        $this->assertSame(substr($token, -4), $client->token_last_four);

        // The token authenticates AS the ApiClient and carries only its scopes.
        $model = PersonalAccessToken::findToken($token);
        $this->assertInstanceOf(ApiClient::class, $model->tokenable);
        $this->assertSame($client->id, $model->tokenable->id);
        $this->assertTrue($model->can('order'));
        $this->assertFalse($model->can('status')); // not granted
    }

    public function test_unknown_scopes_are_dropped_and_empty_falls_back_to_catalogue(): void
    {
        $dev = User::factory()->create();

        ['client' => $bad] = $this->service()->create($dev, 'x', ['order', 'delete_everything', 'wallet.drain']);
        $this->assertSame(['order'], $bad->scopes); // junk scopes stripped

        ['client' => $empty] = $this->service()->create($dev, 'y', []);
        $this->assertSame(['catalogue'], $empty->scopes); // safe read-only default
    }

    public function test_rotating_a_token_kills_the_old_one(): void
    {
        $dev = User::factory()->create();
        ['client' => $client, 'token' => $old] = $this->service()->create($dev, 'app', ['catalogue']);

        $new = $this->service()->rotate($client->fresh());

        $this->assertNull(PersonalAccessToken::findToken($old)); // old key dead
        $this->assertNotNull(PersonalAccessToken::findToken($new));
        $this->assertSame(1, $client->fresh()->tokens()->count()); // exactly one live token
    }

    public function test_revoking_disables_the_client_and_removes_its_tokens(): void
    {
        $dev = User::factory()->create();
        ['client' => $client, 'token' => $token] = $this->service()->create($dev, 'app', ['catalogue']);

        $this->service()->revoke($client);

        $this->assertFalse($client->fresh()->is_active);
        $this->assertFalse($client->fresh()->usable());
        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_setting_scopes_reissues_the_token_with_the_new_abilities(): void
    {
        $dev = User::factory()->create();
        ['client' => $client, 'token' => $old] = $this->service()->create($dev, 'app', ['catalogue']);

        $new = $this->service()->setScopes($client, ['catalogue', 'quote', 'order']);

        $this->assertNull(PersonalAccessToken::findToken($old));
        $model = PersonalAccessToken::findToken($new);
        $this->assertTrue($model->can('quote'));
        $this->assertTrue($model->can('order'));
        $this->assertEqualsCanonicalizing(['catalogue', 'quote', 'order'], $client->fresh()->scopes);
    }

    public function test_a_client_of_an_inactive_owner_is_not_usable(): void
    {
        $dev = User::factory()->create(['is_active' => false]);
        ['client' => $client] = $this->service()->create($dev, 'app', ['catalogue']);

        $this->assertFalse($client->usable()); // owner suspended → client can't trade
    }
}
