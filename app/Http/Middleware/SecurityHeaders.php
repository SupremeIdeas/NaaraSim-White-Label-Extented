<?php

namespace App\Http\Middleware;

use App\Support\BrandSettings;
use App\Support\ConvaiWidget;
use App\Support\SecuritySettings;
use App\Support\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers (blueprint Sections 19.2 & 30): the baseline set,
 * plus a Content-Security-Policy tuned for the TALL stack and HSTS over HTTPS.
 * The CSP + HSTS are config-driven (config/security.php) so they can be tuned
 * without a code change.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            // microphone=(self): the support-chat voice-note feature
            // (resources/js/support-voice.js, getUserMedia({audio: true}))
            // needs it for same-origin content. geolocation=(self): the chat
            // composer's Location button calls navigator.geolocation, which
            // `geolocation=()` blocked silently (the earlier "unused" note was
            // wrong). camera stays locked at () — attach-camera is a native
            // file picker, not getUserMedia.
            'Permissions-Policy' => 'geolocation=(self), microphone=(self), camera=()',
        ];

        // CSP + HSTS are admin-toggleable at runtime (config is the default).
        if (SecuritySettings::cspEnabled() && filled(config('security.csp.policy'))) {
            $policy = $this->policyWithTurnstile((string) config('security.csp.policy'));
            $policy = $this->policyWithConvai($policy);
            $policy = $this->policyWithGoogleFonts($policy);
            $policy = $this->policyWithTrustpilot($policy);
            $policy = $this->policyWithTrackingAndGateways($policy);
            if (class_exists(\App\Support\Crypto\CryptoCsp::class)) {
                $policy = \App\Support\Crypto\CryptoCsp::apply($policy, $request);   // crypto top-up: wallet-connect origins on that one page
            }
            $headers['Content-Security-Policy'] = $policy;
        }

        if (SecuritySettings::hstsEnabled() && $request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.config('security.hsts.max_age').'; includeSubDomains';
        }

        // Do not advertise the PHP version (attackers pick exploits by version).
        if (! headers_sent()) {
            @header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        foreach ($headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }

    /**
     * When Turnstile is active, whitelist Cloudflare's origin in exactly the two
     * directives its widget needs (script-src + frame-src) and nowhere else, so
     * the challenge loads without loosening the policy for anything else.
     */
    private function policyWithTurnstile(string $policy): string
    {
        if (! Turnstile::active()) {
            return $policy;
        }

        $origin = Turnstile::ORIGIN;
        $directives = array_map('trim', explode(';', $policy));
        $sawFrame = false;

        foreach ($directives as $i => $directive) {
            if (str_starts_with($directive, 'script-src ') && ! str_contains($directive, $origin)) {
                $directives[$i] = $directive.' '.$origin;
            }
            if (str_starts_with($directive, 'frame-src ')) {
                $sawFrame = true;
                if (! str_contains($directive, $origin)) {
                    $directives[$i] = $directive.' '.$origin;
                }
            }
        }

        if (! $sawFrame) {
            $directives[] = "frame-src 'self' ".$origin;
        }

        return implode('; ', array_filter($directives));
    }

    /** Payment-gateway checkout hosts a classic HTML form may post to (and be redirected to). Redirects issued from Livewire/JS are not affected by form-action; this keeps a plain form working too. */
    private const GATEWAY_FORM_TARGETS = [
        'https://*.paystack.com', 'https://*.flutterwave.com', 'https://*.fincra.com', 'https://*.paypal.com', 'https://*.stripe.com',
        'https://*.nowpayments.io', 'https://*.cryptomus.com', 'https://*.binance.com', 'https://*.coinpayments.net', 'https://*.payssion.com', 'https://*.paga.com',
    ];

    /**
     * Additive CSP allowances, each only while it is needed: Google Analytics / Meta Pixel origins once the admin saved an ID (the snippets are otherwise
     * blocked), and the payment-gateway hosts a plain form may post to. Never removes or loosens anything else.
     */
    private function policyWithTrackingAndGateways(string $policy): string
    {
        $add = [];
        if (\App\Support\Tracking::gaId()) {
            $add['script-src'][] = 'https://www.googletagmanager.com';
            $add['connect-src'] = array_merge($add['connect-src'] ?? [], ['https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.googletagmanager.com']);
        }
        if (\App\Support\Tracking::pixelId()) {
            $add['script-src'][] = 'https://connect.facebook.net';
            $add['connect-src'] = array_merge($add['connect-src'] ?? [], ['https://www.facebook.com', 'https://connect.facebook.net']);
        }
        // Uploads (voice notes, attachments, pictures): when the upload disk is S3/Wasabi, Livewire sends the file straight from the browser to the bucket, so that
        // host must be reachable from the page or every upload fails silently. Same-origin (local disk) installs need nothing.
        if (class_exists(\Livewire\Features\SupportFileUploads\FileUploadConfiguration::class) && \Livewire\Features\SupportFileUploads\FileUploadConfiguration::isUsingS3()) {
            $disk = (string) (config('livewire.temporary_file_upload.disk') ?: config('filesystems.default'));
            $cfg = (array) config('filesystems.disks.'.strtolower($disk), []);
            $host = parse_url((string) ($cfg['endpoint'] ?? ''), PHP_URL_HOST) ?: (! empty($cfg['region']) ? 's3.'.$cfg['region'].'.amazonaws.com' : null);
            if (is_string($host) && preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
                $add['connect-src'] = array_merge($add['connect-src'] ?? [], ['https://'.$host, 'https://*.'.$host]);
            }
        }
        $add['form-action'] = array_merge(self::GATEWAY_FORM_TARGETS, (array) config('security.csp.form_action_extra', []));

        $directives = array_map('trim', explode(';', $policy));
        $seen = [];
        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $seen[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directive .= ' '.$origin;
                        }
                    }
                    $directives[$i] = $directive;
                }
            }
        }
        // A directive the base policy never set falls back to default-src, so only create the ones we must widen.
        foreach (['script-src' => "'self'", 'connect-src' => "'self'"] as $name => $base) {
            if (! isset($seen[$name]) && isset($add[$name])) {
                $directives[] = $name.' '.$base.' '.implode(' ', $add[$name]);
            }
        }

        return implode('; ', array_filter($directives));
    }

    /**
     * When the ElevenLabs Convai widget is active, add exactly the ElevenLabs
     * origins its embed needs (script/connect/frame/font + a blob worker) and
     * nowhere else. Gated on ConvaiWidget::active(), so the strict default policy
     * is untouched until an admin turns the widget on. (Task #17.)
     */
    private function policyWithConvai(string $policy): string
    {
        if (! ConvaiWidget::active()) {
            return $policy;
        }

        $add = ConvaiWidget::CSP;
        $directives = array_map('trim', explode(';', $policy));
        $present = [];

        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directive .= ' '.$origin;
                        }
                    }
                    $directives[$i] = $directive;
                }
            }
        }

        // Directives not already in the policy (e.g. worker-src) are appended.
        foreach ($add as $name => $origins) {
            if (empty($present[$name])) {
                $directives[] = $name.' '.implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }

    /**
     * When the admin font system (Branding page) has a Google Font selected,
     * allow exactly Google's two font-serving origins — the stylesheet from
     * fonts.googleapis.com (style-src) and the actual font files from
     * fonts.gstatic.com (font-src) — and nowhere else. Gated on
     * BrandSettings::usesGoogleFont(), so the strict default policy is
     * untouched on an unconfigured install.
     */
    /** Trustpilot widget origins (admin-editable list), added only while the reviews layer is live. */
    private function policyWithTrustpilot(string $policy): string
    {
        if (! \App\Support\Trustpilot\TrustpilotSettings::cspActive()) {
            return $policy;
        }
        $add = \App\Support\Trustpilot\TrustpilotSettings::cspHosts();
        $directives = array_map('trim', explode(';', $policy));
        $present = [];
        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directives[$i] .= ' '.$origin;
                        }
                    }
                }
            }
        }
        foreach ($add as $name => $origins) {
            if (empty($present[$name]) && $origins !== []) {
                $directives[] = $name." 'self' ".implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }

    private function policyWithGoogleFonts(string $policy): string
    {
        if (! BrandSettings::usesGoogleFont()) {
            return $policy;
        }

        $add = [
            'style-src' => ['https://fonts.googleapis.com'],
            'font-src' => ['https://fonts.gstatic.com'],
        ];
        $directives = array_map('trim', explode(';', $policy));
        $present = [];

        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directive .= ' '.$origin;
                        }
                    }
                    $directives[$i] = $directive;
                }
            }
        }

        foreach ($add as $name => $origins) {
            if (empty($present[$name])) {
                $directives[] = $name.' '.implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }
}
