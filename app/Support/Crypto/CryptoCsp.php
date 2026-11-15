<?php

namespace App\Support\Crypto;

use Illuminate\Http\Request;

/** Adds the Reown / WalletConnect origins to the CSP on the crypto top-up page ONLY (research log R3). Exact hosts, no wildcards beyond the documented ones. */
class CryptoCsp
{
    private const CONNECT = ['https://rpc.walletconnect.org', 'https://rpc.walletconnect.com', 'https://relay.walletconnect.org', 'https://relay.walletconnect.com', 'wss://relay.walletconnect.org', 'wss://relay.walletconnect.com',
        'https://pulse.walletconnect.org', 'https://pulse.walletconnect.com', 'https://api.web3modal.org', 'https://api.web3modal.com', 'https://keys.walletconnect.com', 'https://keys.walletconnect.org',
        'https://notify.walletconnect.com', 'https://echo.walletconnect.com', 'https://push.walletconnect.com', 'https://explorer-api.walletconnect.com', 'https://verify.walletconnect.org', 'https://verify.walletconnect.com',
        'wss://www.walletlink.org', 'https://cca-lite.coinbase.com'];

    private const FRAME = ['https://verify.walletconnect.com', 'https://verify.walletconnect.org', 'https://secure.walletconnect.com', 'https://secure.walletconnect.org'];

    private const FONT = ['https://fonts.reown.com', 'https://fonts.gstatic.com'];

    public static function apply(string $policy, Request $request): string
    {
        if (! $request->is('wallet/crypto')) {
            return $policy;
        }
        $add = ['connect-src' => self::CONNECT, 'frame-src' => self::FRAME, 'font-src' => self::FONT];
        $directives = array_map('trim', explode(';', $policy));
        foreach ($add as $name => $origins) {
            $hit = false;
            foreach ($directives as $i => $d) {
                if (str_starts_with($d, $name.' ')) {
                    $hit = true;
                    $directives[$i] = $d.' '.implode(' ', array_filter($origins, fn ($o) => ! str_contains($d, $o)));
                }
            }
            if (! $hit) {
                $directives[] = $name." 'self' ".implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }
}
