<?php

namespace Tests\Feature\Crypto;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoLinkedWallet;
use App\Models\Crypto\CryptoRpcEndpoint;
use App\Models\Setting;
use App\Models\User;
use App\Services\Crypto\Deposits\DepositProcessor;
use App\Services\Crypto\Deposits\DepositScanner;
use App\Services\Crypto\Support\DepositStatus;
use Database\Seeders\CryptoChainsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DepositFlowTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://polygon.example.com/KEY';
    private const OURS = '0x2ccf54deed6f5ab61f527d987ddc1bb97ff10b86';
    private const USDC = '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359';
    private const SENDER = '0x1111111111111111111111111111111111111111';
    private const TX = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const BLOCK = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private int $head = 1000;
    private string $amountHex = '0x2faf080';   // 50 USDC (6 decimals)
    private bool $inChain = true;

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

        Setting::setValue('crypto.enabled', 1, 'crypto');
        CryptoChain::where('chain_id', 137)->update(['enabled' => true, 'credit_enabled' => true]);
        CryptoRpcEndpoint::query()->delete();
        CryptoRpcEndpoint::create(['chain_id' => 137, 'role' => 'primary', 'url_encrypted' => self::URL]);
        $this->fakeRpc();
    }

    private function log(): array
    {
        return ['address' => self::USDC, 'topics' => ['0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef', '0x'.str_repeat('0', 24).substr(self::SENDER, 2), '0x'.str_repeat('0', 24).substr(self::OURS, 2)],
            'data' => '0x'.str_pad(substr($this->amountHex, 2), 64, '0', STR_PAD_LEFT), 'transactionHash' => self::TX, 'blockHash' => self::BLOCK, 'blockNumber' => '0x3e0', 'logIndex' => '0x1'];
    }

    private function fakeRpc(): void
    {
        Http::fake([self::URL => function ($req) {
            $m = $req['method'];
            $r = match ($m) {
                'eth_chainId' => '0x89',
                'eth_blockNumber' => '0x'.dechex($this->head),
                'eth_getLogs' => $this->head >= 992 ? [$this->log()] : [],
                'eth_getBlockByNumber' => ['number' => '0x'.dechex($this->head - 2), 'hash' => self::BLOCK],
                'eth_getTransactionReceipt' => $this->inChain ? ['blockHash' => self::BLOCK, 'status' => '0x1', 'logs' => [$this->log()]] : null,
                default => null,
            };

            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => $r]);
        }]);
    }

    private function scan(): void
    {
        app(DepositScanner::class)->scan(CryptoChain::where('chain_id', 137)->first());
    }

    private function link(User $u): void
    {
        CryptoLinkedWallet::create(['user_id' => $u->id, 'address' => self::SENDER, 'verified_at' => now()]);
    }

    public function test_official_addresses_and_issuer_tokens_are_seeded_active_by_default(): void
    {
        $this->assertTrue(CryptoDepositAddress::where('chain_id', 137)->where('address', self::OURS)->where('status', 'active')->exists());
        $this->assertTrue(CryptoDepositAddress::where('chain_id', 56)->where('address', '0x163af1fd68631cf397b8878d754c058b5427c1ac')->exists());
        $this->assertTrue(CryptoDepositAddress::where('chain_id', 1)->where('address', self::OURS)->exists());
        $this->assertFalse((bool) \App\Models\Crypto\CryptoToken::where('chain_id', 56)->where('symbol', 'USDC')->value('enabled'), 'bridged BNB tokens ship disabled');
        $this->assertTrue((bool) \App\Models\Crypto\CryptoToken::where('chain_id', 137)->where('symbol', 'USDC')->value('enabled'));
    }

    public function test_a_finalized_deposit_from_a_linked_wallet_is_credited_exactly_once(): void
    {
        $u = User::factory()->create();
        $this->link($u);
        $this->scan();
        $dep = CryptoDeposit::firstOrFail();
        $this->assertEqualsWithDelta(50.0, (float) $dep->usd_amount, 0.00001);
        $this->assertSame('detected', $dep->status);

        $this->scan();   // re-scanning the same range never duplicates
        $this->assertSame(1, CryptoDeposit::count());

        $dep = app(DepositProcessor::class)->process($dep);
        $this->assertSame('credited', $dep->status);
        $this->assertSame($u->id, (int) $dep->attributed_user_id);
        $this->assertEqualsWithDelta(50.0, app(\App\Services\Wallet\WalletService::class)->spendableUsd($u->fresh()), 0.0001);

        app(DepositProcessor::class)->process($dep);   // a replay cannot pay twice
        $this->assertEqualsWithDelta(50.0, app(\App\Services\Wallet\WalletService::class)->spendableUsd($u->fresh()), 0.0001);
    }

    public function test_a_transfer_from_an_unlinked_wallet_waits_unclaimed_and_credits_nothing(): void
    {
        $this->scan();
        $dep = app(DepositProcessor::class)->process(CryptoDeposit::firstOrFail());
        $this->assertSame('unclaimed', $dep->status);
    }

    public function test_a_transfer_that_left_the_chain_is_reorged_and_never_credited(): void
    {
        $u = User::factory()->create();
        $this->link($u);
        $this->scan();
        $this->inChain = false;
        $dep = app(DepositProcessor::class)->process(CryptoDeposit::firstOrFail());
        $this->assertSame('reorged', $dep->status);
        $this->assertEqualsWithDelta(0.0, app(\App\Services\Wallet\WalletService::class)->spendableUsd($u->fresh()), 0.0001);
    }

    public function test_big_deposits_are_held_for_review_and_credit_only_after_admin_approval(): void
    {
        $u = User::factory()->create();
        $this->link($u);
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\AlertAdminJob::class]);
        $this->amountHex = '0x'.dechex(600 * 1000000);   // 600 USDC: above the 500 review line
        $this->scan();
        $dep = app(DepositProcessor::class)->process(CryptoDeposit::firstOrFail());
        $this->assertSame('held_review', $dep->status);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'crypto_deposit_held');
        $this->assertEqualsWithDelta(0.0, app(\App\Services\Wallet\WalletService::class)->spendableUsd($u->fresh()), 0.0001);
        $dep = app(DepositProcessor::class)->approveHeld($dep, 1);
        $this->assertSame('credited', $dep->status);
        $this->assertEqualsWithDelta(600.0, app(\App\Services\Wallet\WalletService::class)->spendableUsd($u->fresh()), 0.0001);
    }

    public function test_the_credit_pause_holds_everything_back(): void
    {
        $u = User::factory()->create();
        $this->link($u);
        Setting::setValue('crypto.credit_paused', 1, 'crypto');
        $this->scan();
        $dep = app(DepositProcessor::class)->process(CryptoDeposit::firstOrFail());
        $this->assertSame(DepositStatus::PausedHold->value, $dep->status);
    }

    public function test_a_transfer_from_a_token_off_the_allow_list_is_ignored(): void
    {
        \App\Models\Crypto\CryptoToken::where('chain_id', 137)->delete();
        $this->assertNull(app(DepositScanner::class)->scan(CryptoChain::where('chain_id', 137)->first()));
        $this->assertSame(0, CryptoDeposit::count());
    }
}
