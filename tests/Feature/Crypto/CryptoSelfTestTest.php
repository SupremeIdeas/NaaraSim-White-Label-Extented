<?php

namespace Tests\Feature\Crypto;

use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoToken;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Crypto\Health\CryptoSelfTest;
use Database\Seeders\CryptoChainsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CryptoSelfTestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new CryptoChainsSeeder)->run();
        foreach ([1, 137, 56] as $cid) {
            \App\Models\Crypto\CryptoDepositAddress::firstOrCreate(['chain_id' => $cid, 'address' => '0x2ccf54deed6f5ab61f527d987ddc1bb97ff10b86'], ['label' => 'Test', 'status' => 'active']);
        }
        \App\Models\Crypto\CryptoDepositAddress::firstOrCreate(['chain_id' => 56, 'address' => '0x163af1fd68631cf397b8878d754c058b5427c1ac'], ['label' => 'Test', 'status' => 'active']);
        \App\Models\Crypto\CryptoChain::whereIn('chain_id', [1, 137])->update(['enabled' => true, 'credit_enabled' => true]);
        \App\Models\Setting::setValue('crypto.enabled', 1, 'crypto');

    }

    private function chk(string $key): array
    {
        foreach ((new CryptoSelfTest)->offline() as $c) {
            if ($c['key'] === $key) {
                return $c;
            }
        }
        $this->fail("no check $key");
    }

    private function deposit(array $over = []): CryptoDeposit
    {
        $tok = CryptoToken::where('chain_id', 137)->firstOrFail();

        return CryptoDeposit::create($over + ['chain_id' => 137, 'tx_hash' => '0x'.str_repeat('a', 64), 'log_index' => 1, 'block_number' => 5, 'block_hash' => '0x'.str_repeat('b', 64), 'token_id' => $tok->id,
            'from_address' => '0x'.str_repeat('1', 40), 'to_address' => '0x'.str_repeat('2', 40), 'raw_amount' => '1000000', 'usd_amount' => 1, 'status' => 'detected']);
    }

    public function test_the_code_vectors_pass_on_a_healthy_build(): void
    {
        $this->assertSame('ok', $this->chk('maths')['status']);
        if (\App\Services\Crypto\Link\SignatureVerifier::available()) {
            $this->assertSame('ok', $this->chk('signatures')['status']);
        }
        $this->assertSame('ok', $this->chk('contracts')['status']);
        $this->assertSame('ok', $this->chk('addresses')['status']);
        $this->assertSame('ok', $this->chk('ledger')['status']);
    }

    public function test_a_tampered_token_contract_or_peg_is_a_failure(): void
    {
        CryptoToken::where('chain_id', 137)->update(['contract' => '0x'.str_repeat('d', 40)]);
        $this->assertSame('fail', $this->chk('contracts')['status']);
        CryptoToken::where('chain_id', 137)->update(['contract' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359', 'peg_multiplier' => 1.5]);
        $this->assertSame('fail', $this->chk('contracts')['status']);
    }

    public function test_the_ledger_check_catches_a_missing_credit_and_a_credit_with_no_deposit(): void
    {
        $this->deposit(['status' => 'credited', 'credit_reference' => 'topup:crypto:137:x:1']);
        $this->assertSame('fail', $this->chk('ledger')['status'], 'credited with no wallet credit behind it');
        $u = User::factory()->create();
        \App\Models\WalletTransaction::create(['user_id' => $u->id, 'type' => 'credit', 'amount' => 5, 'currency' => 'USD', 'balance_before' => 0, 'balance_after' => 5, 'reference' => 'topup:crypto:137:ghost:9', 'status' => 'completed']);
        $this->assertSame('fail', $this->chk('ledger')['status']);
    }

    public function test_a_verified_deposit_that_never_got_credited_is_a_failure(): void
    {
        $d = $this->deposit(['status' => 'credit_pending']);
        $d->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
        $this->assertSame('fail', $this->chk('stuck')['status']);
    }

    public function test_the_live_run_checks_every_endpoint_and_stores_the_result(): void
    {
        \App\Models\Crypto\CryptoChain::where('chain_id', 137)->update(['enabled' => true]);
        $ts = '0x'.dechex(now()->timestamp);
        $chain = '0x89';
        Http::fake(['*' => function ($req) use ($ts, &$chain) {
            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => match ($req['method']) { 'eth_chainId' => $chain, default => ['number' => '0x10', 'timestamp' => $ts] }]);
        }]);
        $r = (new CryptoSelfTest)->run();
        $rpc = collect($r)->firstWhere('key', 'rpc_137');
        $this->assertSame('ok', $rpc['status']);
        $this->assertNotNull(CryptoSelfTest::last());
        $chain = '0x1';   // endpoints now answer the wrong chain
        $bad = collect((new CryptoSelfTest)->run())->firstWhere('key', 'rpc_137');
        $this->assertSame('fail', $bad['status']);
    }

    public function test_the_verify_check_is_registered_and_the_command_alerts_on_failure(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\AlertAdminJob::class]);
        CryptoToken::where('chain_id', 137)->update(['contract' => '0x'.str_repeat('d', 40)]);
        Http::fake(['*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x89'])]);
        $this->artisan('crypto:selftest')->assertFailed();
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'crypto_selftest_failed');
    }
}
