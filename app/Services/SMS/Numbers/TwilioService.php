<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\OutOfStockException;
use App\Models\Setting;
use App\Services\SMS\NumberProviderInterface;
use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Twilio — PRIMARY for permanent numbers, voice, and real 2-way SMS
 * (blueprint Section 10.3), billed monthly (Part 14.4). Implements the documented
 * REST API (api.twilio.com/2010-04-01 + pricing.twilio.com/v1) with Basic auth
 * (AccountSid:AuthToken). Endpoints are the official, stable ones — verify in the
 * Twilio sandbox at go-live (rule 1.1). When no key is set every method degrades
 * safely (empty search / OutOfStock) so the platform runs fine without it.
 */
class TwilioService implements NumberProviderInterface, VoiceProviderInterface
{
    private function configured(): bool
    {
        return ! empty(config('services.twilio.account_sid'))
            && ! empty(config('services.twilio.auth_token'));
    }

    // ── Voice (Live Voice — Part A) ─────────────────────────────────────────

    public function available(): bool
    {
        return $this->configured();
    }

    public function attachVoiceWebhook(string $numberSid, string $voiceUrl): void
    {
        if (! $this->configured()) {
            return;
        }
        $this->client()->asForm()
            ->post('/IncomingPhoneNumbers/'.$numberSid.'.json', ['VoiceUrl' => $voiceUrl, 'VoiceMethod' => 'POST'])
            ->throw();
    }

    public function detachVoiceWebhook(string $numberSid): void
    {
        if (! $this->configured()) {
            return;
        }
        $this->client()->asForm()
            ->post('/IncomingPhoneNumbers/'.$numberSid.'.json', ['VoiceUrl' => ''])
            ->throw();
    }

    /**
     * Twilio signs each request: X-Twilio-Signature =
     * base64(HMAC-SHA1(URL + sorted POST key/values, AuthToken)). Constant-time.
     */
    public function verifyWebhook(Request $request, string $url): bool
    {
        $signature = $request->header('X-Twilio-Signature');
        if (! is_string($signature) || ! $this->configured()) {
            return false;
        }

        $params = $request->post();
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, (string) config('services.twilio.auth_token'), true));

        return hash_equals($expected, $signature);
    }

    public function forwardTwiml(string $to, ?string $callerId = null, ?string $fallback = null): string
    {
        $caller = $callerId ? ' callerId="'.htmlspecialchars($callerId, ENT_QUOTES).'"' : '';
        $dial = '<Dial'.$caller.' timeout="20"><Number>'.htmlspecialchars($to, ENT_QUOTES).'</Number></Dial>';
        if ($fallback) {
            $dial .= '<Dial'.$caller.'><Number>'.htmlspecialchars($fallback, ENT_QUOTES).'</Number></Dial>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.$dial.'</Response>';
    }

    public function bridgeCall(string $from, string $to, string $twimlUrl): string
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Twilio is not configured.');
        }

        return (string) $this->client()->asForm()
            ->post('/Calls.json', ['To' => $to, 'From' => $from, 'Url' => $twimlUrl])
            ->throw()->json('sid');
    }

    /**
     * A Twilio Access Token is a JWT signed (HS256) with the API Key SECRET,
     * carrying a Voice grant scoped to the outbound TwiML Application. Shape per
     * twilio.com/docs (header cty "twilio-fpa;v=1"). We build it by hand — no SDK
     * — so the dialer stays dependency-light and CSP-safe. Uses the standalone
     * API Key (never the account auth token) so it can be rotated independently.
     */
    public function accessToken(string $identity, int $ttl = 3600): string
    {
        $keySid = (string) config('services.twilio.api_key_sid');
        $keySecret = (string) config('services.twilio.api_key_secret');
        $accountSid = (string) config('services.twilio.account_sid');
        $appSid = (string) config('services.twilio.twiml_app_sid');

        if ($keySid === '' || $keySecret === '' || $accountSid === '' || $appSid === '') {
            throw new OutOfStockException('Twilio dialer is not configured.');
        }

        $now = time();
        $header = ['typ' => 'JWT', 'alg' => 'HS256', 'cty' => 'twilio-fpa;v=1'];
        $payload = [
            'jti' => $keySid.'-'.$now,
            'iss' => $keySid,
            'sub' => $accountSid,
            'iat' => $now,
            'exp' => $now + $ttl,
            'grants' => [
                'identity' => $identity,
                'voice' => [
                    'incoming' => ['allow' => true],
                    'outgoing' => ['application_sid' => $appSid],
                ],
            ],
        ];

        $segments = [
            $this->base64url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64url(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $keySecret, true);
        $segments[] = $this->base64url($signature);

        return implode('.', $segments);
    }

    /**
     * Live wholesale per-minute COST (USD) for an outbound call to a destination,
     * from the Twilio Voice Pricing API (pricing.twilio.com/v2). We take the
     * dearest of the returned outbound prices so a quote never under-prices a
     * more expensive route. Falls back to the configured default off-line — the
     * PricingEngine + MarginGuard still floor the retail regardless.
     */
    public function voiceRate(string $destination): float
    {
        $fallback = (float) config('services.twilio.default_voice_cost', 0.02);
        if (! $this->configured()) {
            return $fallback;
        }
        try {
            $res = Http::withBasicAuth(
                (string) config('services.twilio.account_sid'),
                (string) config('services.twilio.auth_token'),
            )->connectTimeout(3)->timeout(20)->get('https://pricing.twilio.com/v2/Voice/Numbers/'.$destination);

            $prices = collect($res->json('outbound_call_prices', []))
                ->pluck('current_price')
                ->filter(fn ($p) => $p !== null)
                ->map(fn ($p) => (float) $p);

            return $prices->isNotEmpty() ? (float) $prices->max() : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function client()
    {
        // 3s connect catches a dead host fast; 30s total covers real
        // number provisioning, which can run longer than a price check.
        return Http::withBasicAuth(
            (string) config('services.twilio.account_sid'),
            (string) config('services.twilio.auth_token'),
        )->baseUrl('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.account_sid'))
            ->connectTimeout(3)
            ->timeout(30);
    }

    /**
     * Search available local numbers. $options: contains (pattern, Twilio meta
     * chars * % + $), sms (bool), voice (bool), limit. Returns masked rows:
     * [['number' => '+1…', 'locality' => '…']].
     */
    public function searchNumbers(string $country, array $options = []): array
    {
        if (! $this->configured()) {
            return [];
        }

        $query = array_filter([
            'Contains' => $options['contains'] ?? null,
            'SmsEnabled' => ($options['sms'] ?? true) ? 'true' : null,
            'VoiceEnabled' => ($options['voice'] ?? true) ? 'true' : null,
            'PageSize' => (int) ($options['limit'] ?? 10),
        ], fn ($v) => $v !== null);

        $res = $this->client()->get('/AvailablePhoneNumbers/'.strtoupper($this->iso($country)).'/Local.json', $query);
        if ($res->failed()) {
            return [];
        }

        return collect($res->json('available_phone_numbers', []))
            ->map(fn ($n) => [
                'number' => (string) ($n['phone_number'] ?? ''),
                'locality' => (string) ($n['locality'] ?? ($n['region'] ?? '')),
            ])
            ->filter(fn ($n) => $n['number'] !== '')
            ->values()
            ->all();
    }

    /**
     * Provision a specific number. $options must carry `number` (from a prior
     * search). Returns the provider ref (SID), the number, and its monthly cost.
     *
     * @return array{provider_ref: string, number: string, monthly_cost: float, capabilities: array}
     */
    public function buyNumber(string $country, array $options = []): array
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Twilio is not configured.');
        }
        $number = $options['number'] ?? null;
        if (! $number) {
            throw new OutOfStockException('No number selected to provision.');
        }

        $res = $this->client()->asForm()->post('/IncomingPhoneNumbers.json', ['PhoneNumber' => $number]);
        if ($res->failed()) {
            throw new OutOfStockException('Twilio could not provision '.$number.'.');
        }

        return [
            'provider_ref' => (string) $res->json('sid'),
            'number' => (string) ($res->json('phone_number') ?: $number),
            'monthly_cost' => $this->monthlyCost($country),
            'capabilities' => (array) $res->json('capabilities', ['sms' => true, 'voice' => true]),
        ];
    }

    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Twilio is not configured.');
        }
        // A media URL upgrades the message to MMS (Twilio fetches the file).
        $payload = array_filter([
            'From' => $from, 'To' => $to, 'Body' => $body,
            'MediaUrl' => $mediaUrl,
        ], fn ($v) => $v !== null && $v !== '');
        $res = $this->client()->asForm()->post('/Messages.json', $payload);
        if ($res->failed()) {
            throw new OutOfStockException('Twilio message send failed.');
        }

        return ['provider_ref' => (string) $res->json('sid'), 'status' => (string) $res->json('status')];
    }

    /**
     * Wholesale cost (USD) to send one outbound SMS segment. Twilio's per-message
     * price isn't returned synchronously on send, so we read the admin-tunable
     * cost setting (falls back to config). Server-side only — never exposed; the
     * retail markup + MarginGuard floor are applied on top by PricingEngine.
     */
    public function outboundSmsCost(string $to): float
    {
        return (float) Setting::getValue(
            'pricing.sms_send_cost.twilio',
            (float) config('services.twilio.default_sms_cost', 0.0079),
        );
    }

    /** Release the number (stops monthly billing). Best-effort — never throws. */
    public function releaseNumber(string $providerRef): void
    {
        if (! $this->configured() || $providerRef === '') {
            return;
        }
        try {
            $this->client()->delete('/IncomingPhoneNumbers/'.$providerRef.'.json');
        } catch (\Throwable) {
            // best-effort; the renewal job stops charging regardless of release success
        }
    }

    /** Live monthly wholesale (USD) via the Twilio Pricing API, config fallback. */
    public function monthlyCost(string $country): float
    {
        $fallback = (float) config('services.twilio.default_monthly_cost', 1.15);
        if (! $this->configured()) {
            return $fallback;
        }
        try {
            $res = Http::withBasicAuth(
                (string) config('services.twilio.account_sid'),
                (string) config('services.twilio.auth_token'),
            )->connectTimeout(3)->timeout(20)->get('https://pricing.twilio.com/v1/PhoneNumbers/Countries/'.strtoupper($this->iso($country)));

            $local = collect($res->json('phone_number_prices', []))
                ->firstWhere('number_type', 'local');
            $price = $local['current_price'] ?? null;

            return $price !== null ? (float) $price : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /** Map a loose country name/code to an ISO-3166 alpha-2 (Twilio expects it). */
    private function iso(string $country): string
    {
        $c = strtolower(trim($country));
        $map = ['usa' => 'US', 'us' => 'US', 'united states' => 'US', 'uk' => 'GB',
            'united kingdom' => 'GB', 'england' => 'GB', 'nigeria' => 'NG', 'ghana' => 'GH',
            'kenya' => 'KE', 'south africa' => 'ZA', 'canada' => 'CA'];

        return $map[$c] ?? strtoupper(substr($country, 0, 2));
    }
}
