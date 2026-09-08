<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\ApiClient;
use App\Models\ApiWalletTransaction;
use App\Models\User;
use App\Services\Api\ApiWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Developer API prepaid ledger (ROADMAP §Layer 2) — same money-safety
 * guarantees as the user wallet: atomic, balance_before/after, idempotent,
 * never overdraws.
 */
class ApiWalletTest extends TestCase
{
    use RefreshDatabase;

    private function client(float $balance = 0): ApiClient
    {
        return ApiClient::create([
            'owner_user_id' => User::factory()->create()->id,
            'name' => 'app',
            'scopes' => ['catalogue', 'order'],
            'prepaid_balance_usd' => $balance,
            'is_active' => true,
        ]);
    }

    private function svc(): ApiWalletService
    {
        return app(ApiWalletService::class);
    }

    public function test_credit_and_debit_move_the_balance_and_record_before_after(): void
    {
        $client = $this->client();

        $c = $this->svc()->credit($client, 10.0, ['reference' => 'topup-1', 'description' => 'Top up']);
        $this->assertSame('0.0000', (string) $c->balance_before);
        $this->assertSame('10.0000', (string) $c->balance_after);
        $this->assertSame('10.0000', (string) $client->fresh()->prepaid_balance_usd);

        $d = $this->svc()->debit($client, 3.5, ['reference' => 'order-1']);
        $this->assertSame('10.0000', (string) $d->balance_before);
        $this->assertSame('6.5000', (string) $d->balance_after);
        $this->assertSame('6.5000', (string) $client->fresh()->prepaid_balance_usd);
    }

    public function test_debit_beyond_balance_throws_and_moves_nothing(): void
    {
        $client = $this->client(2.0);

        try {
            $this->svc()->debit($client, 5.0, ['reference' => 'order-x']);
            $this->fail('expected InsufficientBalanceException');
        } catch (InsufficientBalanceException) {
            // expected
        }

        $this->assertSame('2.0000', (string) $client->fresh()->prepaid_balance_usd); // untouched
        $this->assertSame(0, ApiWalletTransaction::where('type', 'debit')->count());
    }

    public function test_a_repeated_reference_is_idempotent(): void
    {
        $client = $this->client(20.0);

        $first = $this->svc()->debit($client, 4.0, ['reference' => 'order-42']);
        $again = $this->svc()->debit($client, 4.0, ['reference' => 'order-42']);

        $this->assertSame($first->id, $again->id);                       // same row returned
        $this->assertSame('16.0000', (string) $client->fresh()->prepaid_balance_usd); // charged once
        $this->assertSame(1, ApiWalletTransaction::where('reference', 'order-42')->count());
    }

    public function test_refund_returns_funds(): void
    {
        $client = $this->client(1.0);
        $this->svc()->refund($client, 4.0, ['reference' => 'refund:order-1']);

        $this->assertSame('5.0000', (string) $client->fresh()->prepaid_balance_usd);
    }
}
