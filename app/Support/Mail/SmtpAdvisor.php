<?php

namespace App\Support\Mail;

/**
 * Plain-language help for the Email settings screen (blueprint 6.3): checks the port against the encryption choice, turns a raw SMTP failure into the
 * fix, and checks the From domain's SPF / DMARC / DKIM records. Pure functions, no secrets: error text is scrubbed of the username and password before it is shown.
 */
class SmtpAdvisor
{
    /** Common DKIM selectors worth trying: a record can only be looked up by its selector. */
    public const DKIM_SELECTORS = ['default', 'google', 'selector1', 'selector2', 'k1', 'k2', 'mail', 's1', 's2', 'smtp', 'mandrill', 'resend', 'postmark', 'amazonses'];

    /** @return array{ok: bool, message: ?string, fix_scheme: ?string} */
    public static function pairing(string $port, string $scheme): array
    {
        $p = (int) $port;
        if ($p === 465 && $scheme !== 'smtps') {
            return ['ok' => false, 'message' => 'Port 465 expects SSL/TLS from the first byte. Choose "SSL/TLS (port 465)" or use port 587.', 'fix_scheme' => 'smtps'];
        }
        if (in_array($p, [587, 25, 2525], true) && $scheme === 'smtps') {
            return ['ok' => false, 'message' => "Port {$p} starts in plain text and upgrades with STARTTLS. Choose \"STARTTLS\" or use port 465 for SSL/TLS.", 'fix_scheme' => ''];
        }

        return ['ok' => true, 'message' => null, 'fix_scheme' => null];
    }

    /** The reason a send failed, as something a person can act on. Credentials are removed first. */
    public static function explain(\Throwable $e, string $username = '', string $password = ''): string
    {
        $raw = $e->getMessage();
        foreach (array_filter([$username, $password], fn ($v) => strlen($v) >= 3) as $secret) {
            $raw = str_ireplace($secret, '***', $raw);
        }
        $m = strtolower($raw);
        $fix = match (true) {
            str_contains($m, '535') || str_contains($m, 'authentication') || str_contains($m, 'auth') && str_contains($m, 'fail') => 'The server refused the username or password. Re-enter them (many providers need an app password or an API key, not your login password).',
            str_contains($m, 'connection refused') || str_contains($m, 'could not be established') || str_contains($m, 'connection could not') => 'Could not reach the mail server. Check the host and port, and that your hosting allows outgoing mail on that port (some block 25 and 465).',
            str_contains($m, 'timed out') || str_contains($m, 'timeout') => 'The mail server did not answer in time. Check the host and port, or try the other port (587 with STARTTLS, or 465 with SSL/TLS).',
            str_contains($m, 'ssl') || str_contains($m, 'tls') || str_contains($m, 'certificate') || str_contains($m, 'handshake') => 'The secure connection failed. The encryption choice probably does not match the port: 465 needs SSL/TLS, 587 needs STARTTLS.',
            str_contains($m, 'rejected') || str_contains($m, '550') || str_contains($m, '553') || str_contains($m, 'sender') => 'The server rejected the From address. Use an address on a domain you have verified with your mail provider.',
            str_contains($m, 'getaddrinfo') || str_contains($m, 'host') && str_contains($m, 'resolve') => 'The host name could not be found. Check the spelling of the SMTP host.',
            default => null,
        };

        return ($fix ?? 'The mail server returned an error.').' (Server said: '.mb_substr(trim($raw), 0, 160).')';
    }

    /**
     * @param  callable|null  $resolver  fn(string $name): list<string>, the TXT strings of a name (injectable for tests)
     * @return array{domain: string, spf: array{ok: bool, note: string}, dmarc: array{ok: bool, note: string}, dkim: array{ok: bool, note: string}}
     */
    public static function dns(string $fromAddress, ?callable $resolver = null): array
    {
        $domain = strtolower(trim((string) substr(strrchr($fromAddress, '@') ?: '', 1)));
        $txt = $resolver ?? function (string $name): array {
            $rows = @dns_get_record($name, DNS_TXT) ?: [];

            return array_map(fn ($r) => (string) ($r['txt'] ?? implode('', (array) ($r['entries'] ?? []))), $rows);
        };
        if ($domain === '' || ! str_contains($domain, '.')) {
            $no = ['ok' => false, 'note' => 'Enter a valid From address first.'];

            return ['domain' => $domain, 'spf' => $no, 'dmarc' => $no, 'dkim' => $no];
        }
        $spf = collect($txt($domain))->first(fn ($t) => str_starts_with(strtolower($t), 'v=spf1'));
        $dmarc = collect($txt('_dmarc.'.$domain))->first(fn ($t) => str_starts_with(strtoupper($t), 'V=DMARC1'));
        $dkimHit = null;
        foreach (self::DKIM_SELECTORS as $sel) {
            if (collect($txt($sel.'._domainkey.'.$domain))->contains(fn ($t) => str_contains(strtolower($t), 'v=dkim1') || str_contains(strtolower($t), 'p='))) {
                $dkimHit = $sel;
                break;
            }
        }

        return [
            'domain' => $domain,
            'spf' => $spf ? ['ok' => true, 'note' => 'An SPF record exists.'] : ['ok' => false, 'note' => 'No SPF record. Add a TXT record on '.$domain.' that lists your mail provider (it starts with v=spf1).'],
            'dmarc' => $dmarc ? ['ok' => true, 'note' => 'A DMARC policy exists.'] : ['ok' => false, 'note' => 'No DMARC record. Add a TXT record on _dmarc.'.$domain.' starting v=DMARC1; p=none to begin with.'],
            'dkim' => $dkimHit ? ['ok' => true, 'note' => 'A DKIM key was found (selector "'.$dkimHit.'").'] : ['ok' => false, 'note' => 'No DKIM key found under the common selectors. Your provider gives you the exact selector; add its record, or ignore this if your selector is unusual.'],
        ];
    }
}
