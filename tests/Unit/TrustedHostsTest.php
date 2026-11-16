<?php

namespace Tests\Unit;

use App\Support\TrustedHosts;
use PHPUnit\Framework\TestCase;

/** An install follows its OWN domain: a white-label on its own URL is never locked out, and the check stays off whenever it cannot be sure. */
class TrustedHostsTest extends TestCase
{
    private function allows(array $patterns, string $host): bool
    {
        foreach ($patterns as $p) {
            if (preg_match('/'.$p.'/i', $host)) {
                return true;
            }
        }

        return false;
    }

    public function test_an_install_trusts_its_own_domain_with_and_without_www_and_nothing_else(): void
    {
        $p = TrustedHosts::patterns('https://shop.client-brand.com', 'production', '');
        $this->assertTrue($this->allows($p, 'shop.client-brand.com'));
        $this->assertTrue($this->allows($p, 'www.shop.client-brand.com'));
        $this->assertFalse($this->allows($p, 'www.naara.app'));
        $this->assertFalse($this->allows($p, 'evil.example'));
        $this->assertFalse($this->allows($p, 'shop.client-brand.com.evil.example'));
    }

    public function test_extra_hosts_are_added_and_star_switches_the_check_off(): void
    {
        $p = TrustedHosts::patterns('https://a.example-shop.io', 'production', 'staging.example-shop.io, other.io');
        $this->assertTrue($this->allows($p, 'staging.example-shop.io'));
        $this->assertTrue($this->allows($p, 'other.io'));
        $this->assertSame([], TrustedHosts::patterns('https://a.example-shop.io', 'production', '*'));
    }

    public function test_it_stays_off_when_it_cannot_be_sure(): void
    {
        $this->assertSame([], TrustedHosts::patterns('', 'production', ''));
        $this->assertSame([], TrustedHosts::patterns('http://localhost', 'production', ''));
        $this->assertSame([], TrustedHosts::patterns('http://127.0.0.1:8000', 'production', ''));
        $this->assertSame([], TrustedHosts::patterns('https://my.site.test', 'production', ''));
        $this->assertSame([], TrustedHosts::patterns('https://real-domain.com', 'local', ''));
        $this->assertSame([], TrustedHosts::patterns('not a url', 'production', ''));
    }
}
