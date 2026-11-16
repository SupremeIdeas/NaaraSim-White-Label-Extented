<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The security policy must never stand in the way of payments, analytics or other trusted origins: additive allowances only, each exactly when needed. */
class CspCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private function csp(): string
    {
        return (string) $this->get('/login')->headers->get('Content-Security-Policy');
    }

    private function directive(string $csp, string $name): string
    {
        foreach (explode(';', $csp) as $d) {
            if (str_starts_with(trim($d), $name.' ')) {
                return trim($d);
            }
        }

        return '';
    }

    public function test_plain_forms_may_reach_the_payment_gateways_and_nothing_else_loosens(): void
    {
        $csp = $this->csp();
        $form = $this->directive($csp, 'form-action');
        foreach (['https://*.paystack.com', 'https://*.paypal.com', 'https://*.stripe.com', 'https://*.nowpayments.io'] as $o) {
            $this->assertStringContainsString($o, $form);
        }
        $this->assertStringContainsString("'self'", $form);
        $this->assertStringNotContainsString(' *', $form.' ');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_extra_form_targets_can_be_added_from_the_environment(): void
    {
        config(['security.csp.form_action_extra' => ['https://pay.example-bank.test']]);
        $this->assertStringContainsString('https://pay.example-bank.test', $this->directive($this->csp(), 'form-action'));
    }

    public function test_analytics_origins_are_allowed_only_once_an_id_is_saved(): void
    {
        $this->assertStringNotContainsString('googletagmanager', $this->csp());
        \App\Support\Tracking::save('1234567890', 'G-ABCDE12345');
        $csp = $this->csp();
        $this->assertStringContainsString('https://www.googletagmanager.com', $this->directive($csp, 'script-src'));
        $this->assertStringContainsString('https://www.google-analytics.com', $this->directive($csp, 'connect-src'));
        $this->assertStringContainsString('https://connect.facebook.net', $this->directive($csp, 'script-src'));
        $this->assertStringContainsString("'self'", $this->directive($csp, 'connect-src'));
    }
}
