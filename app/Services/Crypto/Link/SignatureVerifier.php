<?php

namespace App\Services\Crypto\Link;

use Elliptic\EC;
use kornrunner\Keccak;

/** EIP-191 `personal_sign` recovery: which address signed this text. Needs a big-number extension: gmp (fast) or bcmath (slower, same result). */
final class SignatureVerifier
{
    public static function available(): bool
    {
        return (extension_loaded('gmp') || extension_loaded('bcmath')) && class_exists(EC::class) && class_exists(Keccak::class);
    }

    /** The lower-case address that produced a 65-byte (r, s, v) hex signature over the message, or null for anything malformed. */
    public static function recover(string $message, string $signature): ?string
    {
        if (! self::available() || preg_match('/^0x[0-9a-fA-F]{130}$/', $signature) !== 1) {
            return null;
        }
        $r = substr($signature, 2, 64);
        $s = substr($signature, 66, 64);
        $v = hexdec(substr($signature, 130, 2));
        $rec = $v >= 27 ? $v - 27 : $v;
        if (! in_array($rec, [0, 1], true)) {
            return null;
        }
        // secp256k1 half-order: reject the malleable high-s twin so one signature has one encoding.
        if (strcmp(strtolower($s), '7fffffffffffffffffffffffffffffff5d576e7357a4501ddfe92f46681b20a0') > 0) {   // fixed-width lower-case hex: string order is numeric order
            return null;
        }
        try {
            $hash = Keccak::hash("\x19Ethereum Signed Message:\n".strlen($message).$message, 256);
            $pub = (new EC('secp256k1'))->recoverPubKey($hash, ['r' => $r, 's' => $s], $rec);
            $xy = substr($pub->encode('hex'), 2);   // drop the 04 prefix: 64 bytes of X||Y

            return '0x'.substr(Keccak::hash(hex2bin($xy), 256), 24);
        } catch (\Throwable) {
            return null;
        }
    }
}
