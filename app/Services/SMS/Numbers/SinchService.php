<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\SmsException;
use App\Models\Setting;
use App\Services\SMS\NumberProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Prompt 12 §3 — Sinch, broad but unverified per-country African voice
 * coverage. `VoiceProviderInterface` is deliberately NOT implemented (same
 * reasoning as VonageService and PROGRESS.md's Prompt 12 entry): Sinch's own
 * coverage detail for Nigeria/Ghana/Kenya/South Africa sits behind an
 * interactive tool this environment couldn't fetch — rather than assume
 * voice parity from Sinch's general "70+ countries" marketing claim, this
 * ships SMS/number-only (same treatment as Plivo) until a human with
 * dashboard access confirms real African voice coverage.
 *
 * Sinch's platform splits credentials across two separate sub-products —
 * Numbers API (project-scoped Basic auth: client_id/client_secret +
 * project_id) and the legacy XMS SMS API (Bearer token + service_plan_id).
 * Endpoints per developers.sinch.com/docs/numbers (available-number,
 * active-number) and developers.sinch.com/docs/sms (batches) — verify
 * against a live sandbox account before enabling in production.
 */
class SinchService implements NumberProviderInterface
{
    private function numbersConfigured(): bool
    {
        return ! empty(config('services.sinch.client_id'))
            && ! empty(config('services.sinch.client_secret'))
            && ! empty(config('services.sinch.project_id'));
    }

    private function smsConfigured(): bool
    {
        return ! empty(config('services.sinch.api_token')) && ! empty(config('services.sinch.service_plan_id'));
    }

    private function configured(): bool
    {
        return $this->numbersConfigured();
    }

    private function numbersClient(): PendingRequest
    {
        if (! $this->numbersConfigured()) {
            throw new SmsException('Sinch is not configured.');
        }
        $projectId = (string) config('services.sinch.project_id');

        return Http::baseUrl(rtrim((string) config('services.sinch.numbers_base_url'), '/')."/projects/{$projectId}")
            ->withBasicAuth(
                (string) config('services.sinch.client_id'),
                (string) config('services.sinch.client_secret'),
            )
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(30);
    }

    private function smsClient(): PendingRequest
    {
        if (! $this->smsConfigured()) {
            throw new SmsException('Sinch SMS is not configured.');
        }

        return Http::withToken((string) config('services.sinch.api_token'))
            ->baseUrl(rtrim((string) config('services.sinch.sms_base_url'), '/').'/'.config('services.sinch.service_plan_id'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(30);
    }

    /** $options: contains (router's generic key — Sinch's numberPattern has
     *  no confirmed exact-position filter, so exactness relies on the
     *  router's own post-fetch matchesSpec() filter, same as Twilio), limit. */
    public function searchNumbers(string $country, array $options = []): array
    {
        if (! $this->numbersConfigured()) {
            return [];
        }

        $query = array_filter([
            'regionCode' => strtoupper($country),
            'type' => 'MOBILE',
            'capabilities' => 'SMS,VOICE',
            'numberPattern' => $options['contains'] ?? $options['ends_with'] ?? null,
            'size' => (int) ($options['limit'] ?? 10),
        ], fn ($v) => $v !== null);

        $res = $this->numbersClient()->get('/availableNumbers', $query);
        if ($res->failed()) {
            return [];
        }

        return collect($res->json('availableNumbers', []))
            ->map(fn ($n) => [
                'number' => (string) ($n['phoneNumber'] ?? ''),
                'locality' => '', // Sinch's available-number response carries no locality field
                'monthly_cost' => (float) data_get($n, 'monthlyPrice.amount', 0),
            ])
            ->filter(fn ($n) => $n['number'] !== '')
            ->values()
            ->all();
    }

    /**
     * Rents a specific number (`:rent` on the exact number, not the
     * US-only `:rentAny` shortcut — the router always passes a number the
     * caller already picked from search()). The rent response's own
     * `capability` array is the real, per-number capability truth — never a
     * blanket claim.
     */
    public function buyNumber(string $country, array $options = []): array
    {
        $number = (string) ($options['number'] ?? '');
        if ($number === '') {
            throw new SmsException('No number selected to provision.');
        }

        $res = $this->numbersClient()->post('/availableNumbers/'.rawurlencode($number).':rent', []);
        if ($res->failed()) {
            throw new SmsException('Sinch could not provision that number.');
        }

        $capability = (array) $res->json('capability', []);

        return [
            'number' => $number,
            'provider_ref' => $number, // Sinch addresses active numbers by the E.164 number itself
            'capabilities' => [
                'sms' => in_array('SMS', $capability, true),
                // Never true unless the rent response explicitly confirms it —
                // Sinch's African voice coverage isn't independently verified
                // (see the class docblock).
                'voice' => in_array('VOICE', $capability, true),
            ],
        ];
    }

    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array
    {
        // The legacy XMS SMS API has no native MMS attachment field; append
        // any media URL as a link, same trade-off VonageService makes.
        $text = $mediaUrl ? trim($body." {$mediaUrl}") : $body;

        $res = $this->smsClient()->post('/batches', [
            'from' => $from, 'to' => [$to], 'body' => $text,
        ]);

        if ($res->failed()) {
            throw new SmsException('Sinch message send failed.');
        }

        return ['provider_ref' => (string) $res->json('id', ''), 'status' => 'sent'];
    }

    public function outboundSmsCost(string $to): float
    {
        return (float) Setting::getValue(
            'pricing.sms_send_cost.sinch',
            (float) config('services.sinch.default_sms_cost', 0.0075),
        );
    }

    public function releaseNumber(string $providerRef): void
    {
        if (! $this->numbersConfigured() || $providerRef === '') {
            return;
        }
        try {
            $this->numbersClient()->post('/activeNumbers/'.rawurlencode($providerRef).':release', []);
        } catch (\Throwable) {
            // best-effort; the renewal job stops charging regardless of release success
        }
    }

    public function monthlyCost(string $country): float
    {
        $fallback = (float) config('services.sinch.default_monthly_cost', 1.00);
        if (! $this->numbersConfigured()) {
            return $fallback;
        }
        try {
            $res = $this->numbersClient()->get('/availableNumbers', ['regionCode' => strtoupper($country), 'type' => 'MOBILE']);
            $price = data_get($res->json(), 'availableNumbers.0.monthlyPrice.amount');

            return $price !== null ? (float) $price : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
