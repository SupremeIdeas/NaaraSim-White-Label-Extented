<?php

namespace Tests\Feature;

use App\Support\Mail\SmtpAdvisor;
use PHPUnit\Framework\TestCase;

class SmtpAdvisorTest extends TestCase
{
    public function test_port_and_encryption_must_pair(): void
    {
        $this->assertTrue(SmtpAdvisor::pairing('587', '')['ok']);
        $this->assertTrue(SmtpAdvisor::pairing('465', 'smtps')['ok']);
        $a = SmtpAdvisor::pairing('465', '');
        $this->assertFalse($a['ok']);
        $this->assertSame('smtps', $a['fix_scheme']);
        $b = SmtpAdvisor::pairing('587', 'smtps');
        $this->assertFalse($b['ok']);
        $this->assertSame('', $b['fix_scheme']);
        $this->assertTrue(SmtpAdvisor::pairing('', '')['ok'], 'an empty port is not judged');
    }

    public function test_failures_become_plain_fixes_and_never_echo_credentials(): void
    {
        $msg = SmtpAdvisor::explain(new \RuntimeException('Failed to authenticate on SMTP server with username "bob@x.com" using the authenticators. 535 secret99 rejected'), 'bob@x.com', 'secret99');
        $this->assertStringContainsString('refused the username or password', $msg);
        $this->assertStringNotContainsString('secret99', $msg);
        $this->assertStringNotContainsString('bob@x.com', $msg);
        $this->assertStringContainsString('Could not reach', SmtpAdvisor::explain(new \RuntimeException('Connection could not be established with host "smtp.x.com:587": Connection refused')));
        $this->assertStringContainsString('secure connection', SmtpAdvisor::explain(new \RuntimeException('SSL: handshake failure')));
    }

    public function test_dns_hints_report_what_exists_and_what_to_add(): void
    {
        $records = ['example.com' => ['v=spf1 include:_spf.mail.com ~all'], '_dmarc.example.com' => [], 'google._domainkey.example.com' => ['v=DKIM1; k=rsa; p=abc']];
        $r = SmtpAdvisor::dns('hello@example.com', fn ($n) => $records[$n] ?? []);
        $this->assertTrue($r['spf']['ok']);
        $this->assertFalse($r['dmarc']['ok']);
        $this->assertStringContainsString('_dmarc.example.com', $r['dmarc']['note']);
        $this->assertTrue($r['dkim']['ok']);
        $this->assertFalse(SmtpAdvisor::dns('not-an-email', fn () => [])['spf']['ok']);
    }
}
