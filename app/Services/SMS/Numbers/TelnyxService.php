<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\OutOfStockException;
use App\Models\Setting;
use App\Services\SMS\NumberProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Telnyx — permanent/voice BACKUP to Twilio, typically cheaper (blueprint
 * Section 10.2). Implements the documented v2 REST API (Bearer auth):
 * /available_phone_numbers, /number_orders, /messages, /phone_numbers. The
 * router tries it after Twilio when Twilio has no number in the region. Verify
 * endpoints in the Telnyx sandbox at go-live (rule 1.1); degrades safely when
 * no key is set.
 */
class TelnyxService implements NumberProviderInterface
{
    private function configured(): bool
    {
        return ! empty(config('services.telnyx.api_key'));
    }

    private function client()
    {
        // 3s connect catches a dead host fast; 30s total covers permanent-number
        // provisioning, which can legitimately run longer than a price check.
        return Http::withToken((string) config('services.telnyx.api_key'))
            ->baseUrl('https://api.telnyx.com/v2')
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(30);
    }

    /**
     * Search available numbers. $options: contains|starts_with|ends_with (Telnyx
     * literal filters — last 4 for contains), limit. Returns [['number','locality']].
     */
    public function searchNumbers(string $country, array $options = []): array
    {
        if (! $this->configured()) {
            return [];
        }

        $filter = ['filter[country_code]' => strtoupper($this->iso($country)), 'filter[limit]' => (int) ($options['limit'] ?? 10)];
        foreach (['ends_with', 'starts_with', 'contains'] as $k) {
            if (! empty($options[$k])) {
                $filter["filter[phone_number][$k]"] = $options[$k];
            }
        }

        $res = $this->client()->get('/available_phone_numbers', $filter);
        if ($res->failed()) {
            return [];
        }

        return collect($res->json('data', []))
            ->map(fn ($n) => [
                'number' => (string) ($n['phone_number'] ?? ''),
                'locality' => (string) data_get($n, 'region_information.0.region_name', ''),
            ])
            ->filter(fn ($n) => $n['number'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{provider_ref: string, number: string, monthly_cost: float, capabilities: array}
     */
    public function buyNumber(string $country, array $options = []): array
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Telnyx is not configured.');
        }
        $number = $options['number'] ?? null;
        if (! $number) {
            throw new OutOfStockException('No number selected to provision.');
        }

        $res = $this->client()->post('/number_orders', ['phone_numbers' => [['phone_number' => $number]]]);
        if ($res->failed()) {
            throw new OutOfStockException('Telnyx could not provision '.$number.'.');
        }

        // The order id is the provider ref; the actual phone_number id is under data.phone_numbers.
        $ref = (string) (data_get($res->json(), 'data.phone_numbers.0.id') ?: data_get($res->json(), 'data.id'));

        // Telnyx will not send or receive SMS on a number until it is assigned to a Messaging Profile. Done as a queued job with backoff, because a fresh
        // order can still be pending for a moment and the assignment needs the finished phone-number record.
        $bought = (string) (data_get($res->json(), 'data.phone_numbers.0.phone_number') ?: $number);
        if (! empty(config('services.telnyx.messaging_profile_id'))) {
            \App\Jobs\AssignTelnyxMessagingProfileJob::dispatch($bought);
        }

        return [
            'provider_ref' => $ref,
            'number' => $bought,
            'monthly_cost' => $this->monthlyCost($country),
            'capabilities' => ['sms' => true, 'voice' => true],
        ];
    }

    /**
     * Assign a purchased number to the configured Messaging Profile (idempotent: assigning the same profile again changes nothing).
     * Endpoint per Telnyx v2 docs (PATCH /phone_numbers/{id}/messaging); verify in the Telnyx sandbox at go-live (rule 1.1).
     *
     * @return bool true when the number is now on the profile
     */
    public function assignMessagingProfile(string $e164): bool
    {
        $profile = (string) config('services.telnyx.messaging_profile_id');
        if (! $this->configured() || $profile === '') {
            return false;
        }
        $found = $this->client()->get('/phone_numbers', ['filter[phone_number]' => $e164]);
        $id = $found->successful() ? (string) data_get($found->json(), 'data.0.id', '') : '';
        if ($id === '') {
            return false;   // not visible yet (the order is still pending): the job retries
        }
        $res = $this->client()->patch("/phone_numbers/{$id}/messaging", ['messaging_profile_id' => $profile]);

        return $res->successful();
    }

    /**
     * Ed25519 over "{telnyx-timestamp}|{raw body}" with the portal public key. Rejects a missing key, a malformed signature, a stale timestamp (5 minute replay window) and
     * anything that fails the check. Never throws: false means "do not trust this request".
     */
    public function verifyWebhook(Request $request, string $url): bool
    {
        $signature = $request->header('telnyx-signature-ed25519');
        $timestamp = $request->header('telnyx-timestamp');
        $publicKey = (string) config('services.telnyx.public_key');
        if (! is_string($signature) || ! is_string($timestamp) || $publicKey === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $sig = base64_decode($signature, true);
        $key = base64_decode($publicKey, true);
        if ($sig === false || $key === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($sig, $timestamp.'|'.$request->getContent(), $key);
        } catch (\Throwable) {
            return false;
        }
    }

    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Telnyx is not configured.');
        }
        // A media URL sends it as MMS (Telnyx accepts a media_urls array).
        $payload = array_filter([
            'from' => $from, 'to' => $to, 'text' => $body,
            'media_urls' => $mediaUrl !== null && $mediaUrl !== '' ? [$mediaUrl] : null,
            // Per-message delivery webhook (takes priority over the profile's): the signed message.finalized report settles or refunds the charge.
            'webhook_url' => route('webhooks.sms-delivery', ['provider' => 'telnyx']),
        ], fn ($v) => $v !== null && $v !== '');
        $res = $this->client()->post('/messages', $payload);
        if ($res->failed()) {
            throw new OutOfStockException('Telnyx message send failed.');
        }

        return ['provider_ref' => (string) data_get($res->json(), 'data.id'), 'status' => 'queued'];
    }

    /**
     * Wholesale cost (USD) to send one outbound SMS segment (admin-tunable
     * setting, config fallback). Server-side only — retail is layered on by
     * PricingEngine and the provider cost is never surfaced.
     */
    public function outboundSmsCost(string $to): float
    {
        return (float) Setting::getValue(
            'pricing.sms_send_cost.telnyx',
            (float) config('services.telnyx.default_sms_cost', 0.004),
        );
    }

    public function releaseNumber(string $providerRef): void
    {
        if (! $this->configured() || $providerRef === '') {
            return;
        }
        try {
            $this->client()->delete('/phone_numbers/'.$providerRef);
        } catch (\Throwable) {
            // best-effort
        }
    }

    public function monthlyCost(string $country): float
    {
        // Telnyx per-number monthly pricing isn't a single public GET; use the
        // admin-set default (money rule 3 — never a hard-coded price literal in a
        // quote path; this is a config value the admin controls).
        return (float) config('services.telnyx.default_monthly_cost', 1.00);
    }

    private function iso(string $country): string
    {
        $c = strtolower(trim($country));
        $map = ['usa' => 'US', 'us' => 'US', 'united states' => 'US', 'uk' => 'GB',
            'united kingdom' => 'GB', 'england' => 'GB', 'nigeria' => 'NG', 'ghana' => 'GH',
            'kenya' => 'KE', 'south africa' => 'ZA', 'canada' => 'CA'];

        return $map[$c] ?? strtoupper(substr($country, 0, 2));
    }
}
