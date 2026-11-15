<?php

namespace Tests\Feature\Crypto;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoRpcEndpoint;
use App\Models\Crypto\CryptoToken;
use App\Models\Setting;
use App\Services\Crypto\Chain\ChainRegistry;
use App\Services\Crypto\Chain\RpcClient;
use App\Services\Crypto\Chain\RpcException;
use App\Services\Crypto\Chain\RpcPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RpcClientTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://polygon.example.com/v2/SECRETKEY';

    private function client(): RpcClient
    {
        return new RpcClient(self::URL, 0);   // no back-off in tests
    }

    private function ok(mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'result' => $result];
    }

    public function test_typed_reads_validate_hex_and_return_numbers(): void
    {
        Http::fake([self::URL => Http::sequence()->push($this->ok('0x89'))->push($this->ok('0x1a2b3c'))]);
        $this->assertSame(137, $this->client()->chainId());
        $this->assertSame(1715004, $this->client()->blockNumber());
    }

    public function test_a_malformed_answer_is_an_error_never_a_guess(): void
    {
        Http::fake([self::URL => Http::response($this->ok('banana'))]);
        $this->expectException(RpcException::class);
        $this->client()->chainId();
    }

    public function test_an_error_never_contains_the_url_or_its_api_key(): void
    {
        Http::fake([self::URL => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32000, 'message' => 'nope']])]);
        try {
            $this->client()->blockNumber();
            $this->fail('expected an RpcException');
        } catch (RpcException $e) {
            $this->assertStringNotContainsString('SECRETKEY', $e->getMessage());
            $this->assertStringContainsString('polygon.example.com', $e->getMessage());
            $this->assertSame(-32000, $e->rpcCode);
            $this->assertFalse($e->retryable);
        }
    }

    public function test_transient_failures_retry_and_a_good_answer_wins(): void
    {
        Http::fake([self::URL => Http::sequence()->push('', 503)->push('', 429)->push($this->ok('0x1'))]);
        $this->assertSame(1, $this->client()->blockNumber());
        Http::assertSentCount(3);
    }

    public function test_a_range_error_is_recognised_so_the_scanner_can_halve_the_window(): void
    {
        Http::fake([self::URL => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32005, 'message' => 'query returned more than 10000 results']])]);
        try {
            $this->client()->getLogs(1, 5000, ['0x'.str_repeat('a', 40)], [null]);
            $this->fail('expected an RpcException');
        } catch (RpcException $e) {
            $this->assertTrue($e->isRangeError());
        }
    }

    public function test_the_circuit_breaker_opens_after_repeated_failures(): void
    {
        Cache::flush();
        Http::fake([self::URL => Http::response('', 500)]);
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->client()->blockNumber();
            } catch (RpcException) {
            }
        }
        Http::assertSentCount(15);   // 5 failed calls x 3 attempts, then the breaker is open
        $before = count(Http::recorded());
        try {
            $this->client()->blockNumber();
        } catch (RpcException $e) {
            $this->assertStringContainsString('paused', $e->getMessage());
        }
        $this->assertSame($before, count(Http::recorded()), 'a paused endpoint is not called');
    }

    public function test_the_pool_refuses_an_endpoint_on_the_wrong_chain(): void
    {
        Cache::flush();
        Http::fake([self::URL => Http::response($this->ok('0x1'))]);   // answers Ethereum
        $pool = new RpcPool(137, $this->client());
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('answers chain 1, expected 137');
        $pool->primary();
    }

    public function test_the_pool_cross_check_detects_a_secondary_that_disagrees(): void
    {
        Cache::flush();
        Http::fake(['https://a.example.com/*' => Http::sequence()->push($this->ok('0x89'))->push($this->ok(['status' => '0x1']))->push($this->ok(['status' => '0x1'])),
            'https://b.example.com/*' => Http::sequence()->push($this->ok('0x89'))->push($this->ok(['status' => '0x0']))->push($this->ok(['status' => '0x1']))]);
        $pool = new RpcPool(137, new RpcClient('https://a.example.com/k', 0), new RpcClient('https://b.example.com/k', 0));
        $r = $pool->crossCheck(fn (RpcClient $c) => $c->call('eth_getTransactionReceipt', ['0x'.str_repeat('a', 64)]));
        $this->assertFalse($r['agreed'], 'disagreement means held_review');
    }

    public function test_the_registry_enforces_every_switch_and_the_allow_list(): void
    {
        $reg = new ChainRegistry;
        CryptoChain::create(['chain_id' => 137, 'name' => 'Polygon PoS']);
        $this->assertFalse($reg->watching(137), 'the feature ships off');
        Setting::setValue('crypto.enabled', 1, 'crypto');
        $this->assertFalse($reg->watching(137), 'the chain is still off');
        CryptoChain::where('chain_id', 137)->update(['enabled' => true]);
        $this->assertTrue($reg->watching(137));
        $this->assertFalse($reg->crediting(137), 'credit is its own switch');
        CryptoChain::where('chain_id', 137)->update(['credit_enabled' => true]);
        $this->assertTrue($reg->crediting(137));
        Setting::setValue('crypto.credit_paused', 1, 'crypto');
        $this->assertFalse($reg->crediting(137), 'the global pause beats everything');
        $this->assertTrue($reg->watching(137), 'a pause stops credit, never recording');

        CryptoToken::create(['chain_id' => 137, 'symbol' => 'USDC', 'contract' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 'decimals' => 6]);
        $this->assertNotNull($reg->token(137, '0x3C499C542CEF5E3811E1192CE70D8CC03D5C3359'), 'contract lookup ignores case');
        $this->assertNull($reg->token(137, '0x'.str_repeat('9', 40)), 'an unknown contract is never a token');

        CryptoDepositAddress::create(['chain_id' => 137, 'address' => '0x'.str_repeat('D', 40), 'status' => 'active']);
        CryptoDepositAddress::create(['chain_id' => 137, 'address' => '0x'.str_repeat('e', 40), 'status' => 'proposed']);
        $this->assertTrue($reg->isOurAddress(137, '0x'.str_repeat('d', 40)));
        $this->assertFalse($reg->isOurAddress(137, '0x'.str_repeat('e', 40)), 'a proposed address is not scanned yet');
        $this->assertNull($reg->pool(137), 'no RPC endpoint, no pool');
        Cache::flush();
        Http::fake([self::URL => Http::response($this->ok('0x89'))]);
        CryptoRpcEndpoint::create(['chain_id' => 137, 'role' => 'primary', 'url_encrypted' => self::URL]);
        $this->assertSame('polygon.example.com/***', $reg->pool(137)->primary()->label());
    }
}
