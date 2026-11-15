<?php

namespace App\Services\Crypto\Chain;

/** A JSON-RPC problem. `retryable` separates "try again later" (timeout, 429, 5xx) from "this answer is wrong" (bad shape, RPC error object). */
class RpcException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly ?int $rpcCode = null)
    {
        parent::__construct($message);
    }

    /** The node refused a log query as too wide or too heavy: the caller halves the block range and tries again. */
    public function isRangeError(): bool
    {
        return (bool) preg_match('/range|too many|limit exceeded|query returned more than|exceed|10000|response size/i', $this->getMessage());
    }
}
