<?php

namespace App\Services\Crypto\Support;

/**
 * The stablecoin contracts we accept, as published by their issuers (Circle, Tether), written down ONCE and independent of the database. The self-test compares the
 * token table against this list, so a tampered or mistyped contract (the classic way a crypto top-up is drained: crediting a worthless look-alike token) is caught.
 * Bridged BNB Smart Chain copies are listed too but are not issuer-published; they stay disabled until the owner signs the risk note.
 */
final class CanonicalAssets
{
    /** chain id => symbol => lower-case contract. */
    public const TOKENS = [
        1 => ['USDC' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', 'USDT' => '0xdac17f958d2ee523a2206206994597c13d831ec7'],
        137 => ['USDC' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359'],
        56 => ['USDT' => '0x55d398326f99059ff775485246999027b3197955', 'USDC' => '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d'],
    ];

    public static function matches(int $chainId, string $symbol, string $contract): bool
    {
        return (self::TOKENS[$chainId][strtoupper($symbol)] ?? null) === strtolower($contract);
    }
}
