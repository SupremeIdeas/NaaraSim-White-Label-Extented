<?php

namespace Tests\Feature;

use App\Rules\PublicUrl;
use App\Support\Security\SsrfGuard;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Module 19 — Security Hardening Matrix (blueprint Section 30).
 */
class SecurityMatrixTest extends TestCase
{
    public function test_responses_carry_the_hardened_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        // No external script origins — the core XSS-injection defense.
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        // Plain HTTP — no HSTS.
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        // HTTPS — HSTS present.
        $this->withServerVariables(['HTTPS' => 'on'])
            ->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_ssrf_guard_blocks_private_and_reserved_hosts(): void
    {
        foreach ([
            'http://127.0.0.1/',                       // loopback
            'http://169.254.169.254/latest/meta-data', // cloud metadata
            'http://10.0.0.1/',                        // RFC1918
            'http://192.168.1.1/admin',                // RFC1918
            'http://[::1]/',                           // IPv6 loopback
            'ftp://example.com/',                      // non-http scheme
        ] as $url) {
            $this->assertFalse(SsrfGuard::isSafe($url), "should block {$url}");
        }
    }

    public function test_ssrf_guard_allows_public_and_allow_listed_hosts(): void
    {
        $this->assertTrue(SsrfGuard::isSafe('http://1.1.1.1/'));       // public IP literal

        config(['security.ssrf_allowed_hosts' => ['internal.svc']]);
        $this->assertTrue(SsrfGuard::isSafe('http://internal.svc/x')); // allow-listed
    }

    public function test_public_url_rule_rejects_private_hosts(): void
    {
        $fails = Validator::make(['u' => 'http://169.254.169.254/'], ['u' => [new PublicUrl]])->fails();
        $this->assertTrue($fails);

        $passes = Validator::make(['u' => 'http://1.1.1.1/'], ['u' => [new PublicUrl]])->passes();
        $this->assertTrue($passes);
    }

    public function test_sessions_are_hardened(): void
    {
        // Non-overridden defaults.
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));

        // Deployments ship the hardened session values.
        $env = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $env);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $env);

        // The config default (absent any env override) encrypts the session.
        $this->assertStringContainsString(
            "env('SESSION_ENCRYPT', true)",
            file_get_contents(config_path('session.php'))
        );
    }
}
