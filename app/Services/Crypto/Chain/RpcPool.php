<?php

namespace App\Services\Crypto\Chain;

use Illuminate\Support\Facades\Cache;

/**
 * A primary and an optional secondary endpoint for ONE chain. Two guards protect us from a misconfigured or hijacked RPC: the endpoint must answer with the chain id we
 * configured (cached 60 s), and when a secondary exists a verification can demand that both endpoints return the same answer (blueprint 4.2).
 */
class RpcPool
{
    public function __construct(public readonly int $chainId, private readonly RpcClient $primary, private readonly ?RpcClient $secondary = null) {}

    public function primary(): RpcClient
    {
        $this->guard($this->primary);

        return $this->primary;
    }

    public function secondary(): ?RpcClient
    {
        if ($this->secondary === null) {
            return null;
        }
        $this->guard($this->secondary);

        return $this->secondary;
    }

    public function hasSecondary(): bool
    {
        return $this->secondary !== null;
    }

    /** The endpoint must be on OUR chain: a wrong answer here would feed another chain's money into ours. */
    private function guard(RpcClient $c): void
    {
        $key = 'crypto:rpc:chain:'.sha1($c->label().$this->chainId.spl_object_id($c));
        $seen = Cache::remember($key, 60, fn () => $c->chainId());
        if ((int) $seen !== $this->chainId) {
            Cache::forget($key);
            throw new RpcException($c->label().": answers chain {$seen}, expected {$this->chainId}", false);
        }
    }

    /**
     * Run the same read on both endpoints and require identical answers. Returns the primary's answer.
     *
     * @return array{value: mixed, agreed: bool|null} agreed null = no secondary configured
     */
    public function crossCheck(callable $read): array
    {
        $a = $read($this->primary());
        $b2 = $this->secondary();
        if ($b2 === null) {
            return ['value' => $a, 'agreed' => null];
        }

        return ['value' => $a, 'agreed' => json_encode($a) === json_encode($read($b2))];
    }
}
