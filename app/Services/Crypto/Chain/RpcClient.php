<?php

namespace App\Services\Crypto\Chain;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Minimal JSON-RPC client for one EVM endpoint. Read-only by construction: it exposes no signing, sending or key handling (blueprint non-negotiable 5).
 *
 * Safety: strict hex validation of every answer, bounded timeouts, a few jittered retries for transient failures only, a per-endpoint circuit breaker, and
 * errors that never contain the URL (it carries the provider API key). A malformed answer is an error, never a guess.
 */
class RpcClient
{
    private const BREAKER_FAILS = 5;

    private const BREAKER_SECONDS = 60;

    private int $id = 0;

    public function __construct(private readonly string $url, private readonly int $backoffMs = 250, private readonly int $attempts = 3) {}

    /** Host only: safe for logs and screens. */
    public function label(): string
    {
        return (parse_url($this->url, PHP_URL_HOST) ?: 'rpc').'/***';
    }

    public function call(string $method, array $params = []): mixed
    {
        $breaker = 'crypto:rpc:open:'.sha1($this->url);
        if (Cache::has($breaker)) {
            throw new RpcException($this->label().': endpoint paused after repeated failures', true);
        }
        $last = null;
        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            try {
                $result = $this->once($method, $params);
                Cache::forget('crypto:rpc:fails:'.sha1($this->url));

                return $result;
            } catch (RpcException $e) {
                $last = $e;
                if (! $e->retryable || $attempt === $this->attempts) {
                    break;
                }
                if ($this->backoffMs > 0) {
                    usleep(random_int((int) ($this->backoffMs * $attempt * 500), $this->backoffMs * $attempt * 1000));   // jittered
                }
            }
        }
        if ($last !== null && $last->retryable) {
            $key = 'crypto:rpc:fails:'.sha1($this->url);
            $n = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $n, now()->addMinutes(5));
            if ($n >= self::BREAKER_FAILS) {
                Cache::put($breaker, 1, now()->addSeconds(self::BREAKER_SECONDS));
            }
        }
        throw $last ?? new RpcException($this->label().': no answer', true);
    }

    private function once(string $method, array $params): mixed
    {
        try {
            $response = Http::connectTimeout(3)->timeout(10)->acceptJson()->asJson()
                ->post($this->url, ['jsonrpc' => '2.0', 'id' => ++$this->id, 'method' => $method, 'params' => $params]);
        } catch (ConnectionException) {
            throw new RpcException($this->label().': connection failed or timed out', true);
        }
        if ($response->status() === 429 || $response->serverError()) {
            throw new RpcException($this->label().': HTTP '.$response->status(), true);
        }
        if (! $response->successful()) {
            throw new RpcException($this->label().': HTTP '.$response->status(), false);
        }
        $body = $response->json();
        if (! is_array($body)) {
            throw new RpcException($this->label().': the answer is not JSON', false);
        }
        if (isset($body['error'])) {
            $err = (array) $body['error'];
            throw new RpcException($this->label().': '.mb_substr((string) ($err['message'] ?? 'RPC error'), 0, 200), false, isset($err['code']) ? (int) $err['code'] : null);
        }
        if (! array_key_exists('result', $body)) {
            throw new RpcException($this->label().': the answer has no result', false);
        }

        return $body['result'];
    }

    // ---- typed reads -------------------------------------------------------------------------------------------------------------------------

    public function chainId(): int
    {
        return (int) Units::hexToDec($this->hexResult($this->call('eth_chainId')));
    }

    public function blockNumber(): int
    {
        return (int) Units::hexToDec($this->hexResult($this->call('eth_blockNumber')));
    }

    /** @return array<string, mixed>|null */
    public function getBlockByNumber(int|string $numberOrTag): ?array
    {
        $arg = is_int($numberOrTag) ? '0x'.dechex($numberOrTag) : $numberOrTag;
        if (! is_int($numberOrTag) && ! in_array($numberOrTag, ['latest', 'finalized', 'safe', 'earliest', 'pending'], true)) {
            throw new \InvalidArgumentException('Bad block tag.');
        }
        $r = $this->call('eth_getBlockByNumber', [$arg, false]);

        return is_array($r) ? $r : null;
    }

    /**
     * @param  list<string>  $addresses  token contracts
     * @param  list<string|null>  $topics  e.g. [TRANSFER_TOPIC, null, paddedTo]
     * @return list<array<string, mixed>>
     */
    public function getLogs(int $from, int $to, array $addresses, array $topics): array
    {
        if ($from < 0 || $to < $from) {
            throw new \InvalidArgumentException('Bad block range.');
        }
        foreach ($addresses as $a) {
            if (! Units::isAddress($a)) {
                throw new \InvalidArgumentException('Bad contract address.');
            }
        }
        $r = $this->call('eth_getLogs', [['fromBlock' => '0x'.dechex($from), 'toBlock' => '0x'.dechex($to), 'address' => array_values($addresses), 'topics' => $topics]]);
        if (! is_array($r)) {
            throw new RpcException($this->label().': eth_getLogs did not return a list', false);
        }

        return array_values(array_filter($r, 'is_array'));
    }

    /** @return array<string, mixed>|null null = not mined yet */
    public function getTransactionReceipt(string $hash): ?array
    {
        if (! Units::isHash($hash)) {
            throw new \InvalidArgumentException('Bad transaction hash.');
        }
        $r = $this->call('eth_getTransactionReceipt', [strtolower($hash)]);

        return is_array($r) ? $r : null;
    }

    private function hexResult(mixed $r): string
    {
        if (! is_string($r) || preg_match('/^0x[0-9a-fA-F]+$/', $r) !== 1) {
            throw new RpcException($this->label().': malformed hex answer', false);
        }

        return $r;
    }
}
