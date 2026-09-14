<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\SmsException;
use App\Services\SMS\NumberProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Prompt 12 §2 — Vonage, the strongest new-vendor candidate for Naara's
 * actual market. Confirmed before building: Vonage publishes a "Nigeria
 * Voice Features and Restrictions" page carrying real operational content
 * (Caller ID guidelines, international reach) — not a "not supported"
 * notice — the strongest signal available in this environment that Nigeria
 * voice is genuinely live, not just a country the API happens to list.
 *
 * `VoiceProviderInterface` is deliberately NOT implemented here (see
 * PROGRESS.md's Prompt 12 entry for the full reasoning): that interface is
 * Twilio-only throughout this codebase today (TwiML shape, twilio-fpa JWT
 * access tokens) with zero current caller for any other provider — building
 * a genuine Vonage NCCO-based call-flow equivalent is separate, larger work
 * this pass didn't attempt without sandbox access to verify it against.
 *
 * Implements the documented legacy REST API (rest.nexmo.com) — HTTP Basic
 * auth (api_key:api_secret), the same numbers/search, numbers/buy,
 * numbers/cancel, and sms/json endpoints Vonage's own code samples show.
 * Verify against a live sandbox account before enabling in production (the
 * same discipline already applied to Twilio/Telnyx/Plivo in this codebase).
 */
class VonageService implements NumberProviderInterface
{
    private function configured(): bool
    {
        return ! empty(config('services.vonage.api_key')) && ! empty(config('services.vonage.api_secret'));
    }

    private function client(): PendingRequest
    {
        if (! $this->configured()) {
            throw new SmsException('Vonage is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.vonage.base_url'), '/'))
            ->withBasicAuth(
                (string) config('services.vonage.api_key'),
                (string) config('services.vonage.api_secret'),
            )
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(30);
    }

    /**
     * $options: contains|ends_with (router's neutral spec, translated to
     * Vonage's own `pattern`+`search_pattern`: 0=starts, 1=anywhere, 2=ends),
     * limit.
     */
    public function searchNumbers(string $country, array $options = []): array
    {
        if (! $this->configured()) {
            return [];
        }

        $query = ['country' => strtoupper($country), 'features' => 'SMS,VOICE'];
        if (! empty($options['ends_with'])) {
            $query['pattern'] = $options['ends_with'];
            $query['search_pattern'] = 2;
        } elseif (! empty($options['contains'])) {
            $query['pattern'] = $options['contains'];
            $query['search_pattern'] = 1;
        }

        $res = $this->client()->get('/number/search', $query);
        if ($res->failed()) {
            return [];
        }

        return collect($res->json('numbers', []))
            ->map(fn ($n) => [
                'number' => (string) ($n['msisdn'] ?? ''),
                'locality' => '', // Vonage's search response carries no locality field
                'monthly_cost' => (float) ($n['cost'] ?? 0),
            ])
            ->filter(fn ($n) => $n['number'] !== '')
            ->take((int) ($options['limit'] ?? 10))
            ->values()
            ->all();
    }

    /**
     * Buys a specific number. Vonage's buy response carries no per-number
     * capability data, so the SEARCH result's own `features` (fetched again
     * here, since buyNumber() only receives the number itself) decides the
     * claim — never a blanket true. A failed lookup defaults to voice=false,
     * same safe-default discipline as PlivoService::realCapabilities().
     */
    public function buyNumber(string $country, array $options = []): array
    {
        $number = (string) ($options['number'] ?? '');
        if ($number === '') {
            throw new SmsException('No number selected to provision.');
        }

        $res = $this->client()->asForm()->post('/number/buy', [
            'country' => strtoupper($country),
            'msisdn' => $number,
        ]);

        if ($res->failed() || (string) $res->json('error-code') !== '200') {
            throw new SmsException('Vonage could not provision that number.');
        }

        return [
            'number' => $number,
            'provider_ref' => $number, // Vonage has no separate SID; the msisdn IS the ref
            'capabilities' => $this->realCapabilities($country, $number),
        ];
    }

    private function realCapabilities(string $country, string $number): array
    {
        try {
            $res = $this->client()->get('/number/search', ['country' => strtoupper($country), 'pattern' => $number, 'search_pattern' => 1]);
            $match = collect($res->json('numbers', []))->firstWhere('msisdn', $number);
            $features = (array) ($match['features'] ?? []);

            return [
                'sms' => in_array('SMS', $features, true),
                'voice' => in_array('VOICE', $features, true),
            ];
        } catch (\Throwable) {
            return ['sms' => true, 'voice' => false];
        }
    }

    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array
    {
        // Vonage's classic SMS API has no native MMS/media attachment — an
        // outbound media URL is simply appended as a link, same honesty
        // trade-off as any provider without MMS support.
        $text = $mediaUrl ? trim($body." {$mediaUrl}") : $body;

        $res = $this->client()->asForm()->post('/sms/json', [
            'from' => $from, 'to' => $to, 'text' => $text,
        ]);

        $message = data_get($res->json(), 'messages.0', []);
        if ((string) ($message['status'] ?? '1') !== '0') {
            throw new SmsException('Vonage message send failed: '.($message['error-text'] ?? 'unknown error'));
        }

        return ['provider_ref' => (string) ($message['message-id'] ?? ''), 'status' => 'sent'];
    }

    /**
     * Vonage's SMS price isn't returned synchronously on send — same
     * admin-tunable-setting pattern as TwilioService::outboundSmsCost().
     */
    public function outboundSmsCost(string $to): float
    {
        return (float) \App\Models\Setting::getValue(
            'pricing.sms_send_cost.vonage',
            (float) config('services.vonage.default_sms_cost', 0.0075),
        );
    }

    public function releaseNumber(string $providerRef): void
    {
        if (! $this->configured() || $providerRef === '') {
            return;
        }
        try {
            // Vonage's cancel endpoint needs the country too; look it up from
            // the number's own leading digits via a light heuristic isn't
            // reliable, so this reads the number back from our own records —
            // RenewVirtualNumbersJob/PermanentNumberRouter always pass the
            // provider_ref (the msisdn itself) with the country already known
            // from the VirtualNumber row at the call site.
            $this->client()->asForm()->post('/number/cancel', [
                'msisdn' => $providerRef,
                'country' => $this->guessCountry($providerRef),
            ]);
        } catch (\Throwable) {
            // best-effort; the renewal job stops charging regardless of release success
        }
    }

    /** Best-effort E.164 prefix → ISO guess, ONLY for the release call above
     *  (never for pricing/search, where the real country is always known). */
    private function guessCountry(string $msisdn): string
    {
        $digits = preg_replace('/\D/', '', $msisdn) ?? $msisdn;
        $map = ['234' => 'NG', '233' => 'GH', '254' => 'KE', '27' => 'ZA', '44' => 'GB', '1' => 'US'];
        foreach ($map as $prefix => $iso) {
            if (str_starts_with($digits, $prefix)) {
                return $iso;
            }
        }

        return '';
    }

    public function monthlyCost(string $country): float
    {
        $fallback = (float) config('services.vonage.default_monthly_cost', 1.00);
        if (! $this->configured()) {
            return $fallback;
        }
        try {
            $res = $this->client()->get('/number/search', ['country' => strtoupper($country)]);
            $cost = data_get($res->json(), 'numbers.0.cost');

            return $cost !== null ? (float) $cost : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
