<?php

namespace Tests\Unit\Crypto;

use App\Services\Crypto\Chain\Units;
use PHPUnit\Framework\TestCase;

class UnitsTest extends TestCase
{
    public function test_six_and_eighteen_decimal_tokens_convert_exactly(): void
    {
        $this->assertSame('12.5', Units::rawToDecimal('12500000', 6));
        $this->assertSame('0.000001', Units::rawToDecimal('1', 6));
        $this->assertSame('1', Units::rawToDecimal('1000000000000000000', 18));
        $this->assertSame('0', Units::rawToDecimal('0', 6));
        $this->assertSame('12.5000', Units::rawToUsd('12500000', 6));
        $this->assertSame('1.0000', Units::rawToUsd('1000000000000000000', 18));
    }

    public function test_dollars_always_round_down_never_up(): void
    {
        $this->assertSame('1.9999', Units::rawToUsd('1999999', 6), '1.999999 credits 1.9999');
        $this->assertSame('0.0000', Units::rawToUsd('99', 6), 'a dust amount credits nothing');
        $this->assertSame('1.0009', Units::rawToUsd('1000999', 6), '1.000999 credits 1.0009');
        $this->assertSame('99', Units::dustRaw('1999999', 6, 4), 'the 99 millionths left over are recorded, never credited');
        $this->assertSame('0', Units::dustRaw('12500000', 6, 4));
    }

    public function test_the_peg_multiplier_scales_and_zero_pauses(): void
    {
        $this->assertSame('99.9000', Units::rawToUsd('100000000', 6, '0.9990'));
        $this->assertSame('0.0000', Units::rawToUsd('100000000', 6, '0'));
        $this->assertSame('100.0000', Units::rawToUsd('100000000', 6, '1.0000'));
    }

    public function test_a_uint256_beyond_64_bits_is_handled_without_overflow(): void
    {
        $max = '115792089237316195423570985008687907853269984665640564039457584007913129639935';   // 2^256 - 1
        $this->assertTrue(Units::isUint($max));
        $this->assertFalse(Units::isUint($max.'0'), 'longer than a uint256 is refused');
        $this->assertSame($max, Units::hexToDec('0x'.str_repeat('f', 64)));
        $this->assertSame('115792089237316195423570985008687907853269984665640564039457.5840', Units::rawToUsd($max, 18));
        $this->assertSame('115792089237316195423570985008687907853269984665640564039457.584007913129639935', Units::rawToDecimal($max, 18));
    }

    public function test_hex_conversion_matches_known_values(): void
    {
        $this->assertSame('0', Units::hexToDec('0x0'));
        $this->assertSame('0', Units::hexToDec('0x'));
        $this->assertSame('255', Units::hexToDec('0xff'));
        $this->assertSame('1000000', Units::hexToDec('0xf4240'));
        $this->assertSame('18446744073709551616', Units::hexToDec('0x10000000000000000'));
        $this->expectException(\InvalidArgumentException::class);
        Units::hexToDec('0xzz');
    }

    public function test_validators_refuse_bad_input(): void
    {
        $this->assertFalse(Units::isUint(''));
        $this->assertFalse(Units::isUint('012'));
        $this->assertFalse(Units::isUint('-1'));
        $this->assertFalse(Units::isUint('1.5'));
        $this->assertTrue(Units::isAddress('0x'.str_repeat('a', 40)));
        $this->assertFalse(Units::isAddress('0x'.str_repeat('a', 39)));
        $this->assertTrue(Units::isHash('0x'.str_repeat('a', 64)));
        $this->expectException(\InvalidArgumentException::class);
        Units::rawToUsd('1', 6, 'abc');
    }

    /** Property: the credited dollars equal an independent string truncation of the exact value (never rounded up, never off by a digit), including beyond 64 bits. */
    public function test_property_the_credit_equals_the_exact_value_truncated_to_four_places(): void
    {
        mt_srand(20261115);
        for ($i = 0; $i < 400; $i++) {
            $decimals = [6, 6, 18, 8, 0][mt_rand(0, 4)];
            $raw = (string) mt_rand(1, PHP_INT_MAX);
            if (mt_rand(0, 1)) {
                $raw .= (string) mt_rand(0, 999999);
            }
            [$int, $frac] = array_pad(explode('.', Units::rawToDecimal($raw, $decimals), 2), 2, '');
            $expected = $int.'.'.substr(str_pad($frac, 4, '0'), 0, 4);
            $this->assertSame($expected, Units::rawToUsd($raw, $decimals), "raw {$raw} with {$decimals} decimals");
        }
    }
}
