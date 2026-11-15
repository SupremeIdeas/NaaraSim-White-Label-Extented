<?php

namespace Tests\Unit\Crypto;

use App\Services\Crypto\Chain\AbiDecoder;
use PHPUnit\Framework\TestCase;

class AbiDecoderTest extends TestCase
{
    private function log(array $over = []): array
    {
        return $over + [
            'address' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            'topics' => [AbiDecoder::TRANSFER_TOPIC, '0x'.str_repeat('0', 24).'1111111111111111111111111111111111111111', '0x'.str_repeat('0', 24).'2222222222222222222222222222222222222222'],
            'data' => '0x'.str_pad('bebc20', 64, '0', STR_PAD_LEFT),   // 12,500,000
            'blockNumber' => '0x64', 'blockHash' => '0x'.str_repeat('b', 64), 'transactionHash' => '0x'.str_repeat('a', 64), 'logIndex' => '0x3', 'removed' => false,
        ];
    }

    public function test_the_transfer_topic_is_the_keccak_of_the_signature(): void
    {
        // keccak256("Transfer(address,address,uint256)"), computed independently with pycryptodome on 2026-11-15; the PHP library check joins this in Phase 5.
        $this->assertSame('0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef', AbiDecoder::TRANSFER_TOPIC);
    }

    public function test_a_well_formed_transfer_decodes_with_lower_case_addresses_and_a_string_amount(): void
    {
        $d = AbiDecoder::decodeTransfer($this->log());
        $this->assertSame('0x3c499c542cef5e3811e1192ce70d8cc03d5c3359', $d['contract']);
        $this->assertSame('0x1111111111111111111111111111111111111111', $d['from']);
        $this->assertSame('0x2222222222222222222222222222222222222222', $d['to']);
        $this->assertSame('12500000', $d['raw_amount']);
        $this->assertSame(3, $d['log_index']);
        $this->assertSame(100, $d['block_number']);
        $this->assertFalse($d['removed']);
    }

    public function test_anything_that_is_not_exactly_a_plain_transfer_is_refused(): void
    {
        $this->assertNull(AbiDecoder::decodeTransfer($this->log(['topics' => [AbiDecoder::TRANSFER_TOPIC, '0x'.str_repeat('0', 64)]])), 'two topics (an NFT-style or malformed event)');
        $this->assertNull(AbiDecoder::decodeTransfer($this->log(['topics' => ['0x'.str_repeat('1', 64)] + $this->log()['topics']])), 'wrong signature');
        $this->assertNull(AbiDecoder::decodeTransfer($this->log(['data' => '0x'.str_repeat('0', 128)])), 'two data words');
        $this->assertNull(AbiDecoder::decodeTransfer($this->log(['data' => '0x'])), 'no data');
        $bad = $this->log();
        $bad['topics'][2] = '0x'.str_repeat('f', 64);   // bits above the 20-byte address
        $this->assertNull(AbiDecoder::decodeTransfer($bad));
        $this->assertNull(AbiDecoder::decodeTransfer($this->log(['transactionHash' => '0x12'])));
    }

    public function test_the_padded_topic_for_a_deposit_address(): void
    {
        $this->assertSame('0x'.str_repeat('0', 24).'abcdefabcdefabcdefabcdefabcdefabcdefabcd', AbiDecoder::padAddress('0xABCDEFabcdefABCDEFabcdefABCDEFabcdefabcd'));
        $this->expectException(\InvalidArgumentException::class);
        AbiDecoder::padAddress('0x123');
    }

    public function test_the_removed_flag_is_carried_through_so_the_verifier_can_refuse_it(): void
    {
        $this->assertTrue(AbiDecoder::decodeTransfer($this->log(['removed' => true]))['removed']);
    }
}
