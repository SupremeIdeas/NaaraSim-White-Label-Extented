<?php

namespace App\Services\Crypto\Chain;

/**
 * Strict decoder for an ERC-20 `Transfer(address,address,uint256)` log. Anything that is not EXACTLY that shape is refused (never "best effort"):
 * three topics, the right signature, 32-byte padded addresses, exactly one 32-byte data word. Anyone can deploy a contract that emits a look-alike
 * event, so the caller must still check the emitting contract against the allow-list; this class only proves the SHAPE.
 */
final class AbiDecoder
{
    /** keccak256("Transfer(address,address,uint256)"), computed independently and asserted by the unit test. */
    public const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /** A 20-byte address left-padded to a 32-byte topic. */
    public static function padAddress(string $address): string
    {
        if (! Units::isAddress($address)) {
            throw new \InvalidArgumentException('Not an address.');
        }

        return '0x'.str_repeat('0', 24).strtolower(substr($address, 2));
    }

    /**
     * @param  array<string, mixed>  $log  one entry of an eth_getLogs / receipt `logs` array
     * @return array{contract: string, from: string, to: string, raw_amount: string, tx_hash: string, log_index: int, block_number: int, block_hash: string, removed: bool}|null null = not a well-formed Transfer
     */
    public static function decodeTransfer(array $log): ?array
    {
        $topics = $log['topics'] ?? null;
        if (! is_array($topics) || count($topics) !== 3 || strtolower((string) $topics[0]) !== self::TRANSFER_TOPIC) {
            return null;
        }
        $from = self::addressFromTopic($topics[1]);
        $to = self::addressFromTopic($topics[2]);
        $data = (string) ($log['data'] ?? '');
        if ($from === null || $to === null || preg_match('/^0x[0-9a-fA-F]{64}$/', $data) !== 1) {
            return null;   // an event with extra or missing data words is not a plain ERC-20 Transfer
        }
        $contract = (string) ($log['address'] ?? '');
        $txHash = (string) ($log['transactionHash'] ?? '');
        $blockHash = (string) ($log['blockHash'] ?? '');
        if (! Units::isAddress($contract) || ! Units::isHash($txHash) || ! Units::isHash($blockHash)) {
            return null;
        }
        try {
            $logIndex = (int) Units::hexToDec((string) ($log['logIndex'] ?? ''));
            $block = (int) Units::hexToDec((string) ($log['blockNumber'] ?? ''));
            $raw = Units::hexToDec($data);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return ['contract' => strtolower($contract), 'from' => $from, 'to' => $to, 'raw_amount' => $raw, 'tx_hash' => strtolower($txHash), 'log_index' => $logIndex,
            'block_number' => $block, 'block_hash' => strtolower($blockHash), 'removed' => (bool) ($log['removed'] ?? false)];
    }

    private static function addressFromTopic(mixed $topic): ?string
    {
        if (! is_string($topic) || preg_match('/^0x0{24}[0-9a-fA-F]{40}$/', $topic) !== 1) {
            return null;   // a topic with bits above the 20-byte address is not an address
        }

        return '0x'.strtolower(substr($topic, 26));
    }
}
