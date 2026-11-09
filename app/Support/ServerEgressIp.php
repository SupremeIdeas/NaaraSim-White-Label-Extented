<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The public IP this server's outbound requests leave from, as the outside world sees it. This is the address a supplier (Paga, VTpass...) must have on its
 * whitelist, and on shared/cPanel hosting it is often NOT the website's own IP. Asked of independent echo services, cached for an hour; null when none answer.
 */
class ServerEgressIp
{
    private const ECHOES = ['https://api.ipify.org', 'https://ifconfig.me/ip', 'https://checkip.amazonaws.com'];

    public static function get(bool $fresh = false): ?string
    {
        if (! $fresh && ($hit = Cache::get('server.egress_ip'))) {
            return $hit;
        }
        foreach (self::ECHOES as $url) {
            try {
                $ip = trim((string) Http::timeout(6)->connectTimeout(3)->get($url)->body());
            } catch (Throwable) {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                Cache::put('server.egress_ip', $ip, 3600);

                return $ip;
            }
        }

        return null;
    }
}
