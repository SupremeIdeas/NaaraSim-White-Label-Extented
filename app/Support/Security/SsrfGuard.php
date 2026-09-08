<?php

namespace App\Support\Security;

/**
 * SSRF defense (blueprint Section 30). Any time the server fetches a URL that
 * could be influenced by user input, run it through here first. It rejects
 * non-HTTP(S) schemes and any host that resolves to a private, loopback,
 * link-local or otherwise reserved address — so an attacker can't pivot a
 * "fetch this URL" feature into the cloud metadata endpoint or an internal
 * service. An explicit allow-list (config/security.php) can permit specific
 * hosts.
 */
class SsrfGuard
{
    public static function isSafe(string $url): bool
    {
        try {
            self::assert($url);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 if the URL is unsafe
     */
    public static function assert(string $url): void
    {
        $parts = parse_url($url);

        $scheme = strtolower($parts['scheme'] ?? '');
        abort_unless(in_array($scheme, ['http', 'https'], true), 422, 'Only http(s) URLs are allowed.');

        $host = $parts['host'] ?? '';
        abort_if($host === '', 422, 'That URL has no host.');

        // Explicit allow-list wins.
        if (in_array(strtolower($host), array_map('strtolower', config('security.ssrf_allowed_hosts', [])), true)) {
            return;
        }

        foreach (self::resolve($host) as $ip) {
            abort_if(self::isBlocked($ip), 422, 'That URL resolves to a private or reserved address and was refused.');
        }
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $host = trim($host, '[]'); // IPv6 literal

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        foreach (['A', 'AAAA'] as $type) {
            $records = @dns_get_record($host, $type === 'A' ? DNS_A : DNS_AAAA) ?: [];
            foreach ($records as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
            }
        }
        $ips = array_values(array_filter($ips));

        // If DNS can't resolve, fail closed — a name we can't verify is unsafe.
        abort_if($ips === [], 422, 'That host could not be resolved.');

        return $ips;
    }

    private static function isBlocked(string $ip): bool
    {
        // Public range only: strip private + reserved. Anything filtered out
        // (loopback, link-local, ULA, RFC1918, metadata 169.254.x, etc.) is
        // blocked.
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
