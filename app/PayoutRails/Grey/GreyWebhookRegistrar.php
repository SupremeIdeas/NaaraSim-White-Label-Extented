<?php

namespace App\PayoutRails\Grey;

use App\Support\Auditor;
use App\Support\Integrations\ProviderWebhooks;
use App\Support\ProviderKeys;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Registers this platform's payout webhook with Grey through Grey's own API (POST /v1/webhooks), so nobody has to copy an address and a secret by hand.
 * It makes up the signing secret itself, sends it to Grey, and only saves it here after Grey accepted the webhook, so the two can never disagree.
 * It refuses to run twice (a saved secret means a webhook already exists) unless forced, and refuses an address Grey could not reach.
 */
class GreyWebhookRegistrar
{
    public const EVENTS = ['transaction.success', 'transaction.failed'];

    public function __construct(private readonly GreyClient $client)
    {
    }

    /** @return array{ok: bool, message: string} */
    public function register(bool $force = false): array
    {
        if (! $this->client->configured()) {
            return ['ok' => false, 'message' => 'Save the Grey API secret key first.'];
        }
        if (filled(config('services.grey.webhook_secret')) && ! $force) {
            return ['ok' => false, 'message' => 'A Grey webhook secret is already saved, so a webhook probably exists. Use "register again" only if you deleted it in Grey.'];
        }
        if (! Route::has('webhooks.payouts')) {
            return ['ok' => false, 'message' => 'This install has no payout webhook address.'];
        }
        if (class_exists(ProviderWebhooks::class) && ($warn = ProviderWebhooks::baseWarning())) {
            return ['ok' => false, 'message' => $warn];
        }
        $url = route('webhooks.payouts', ['provider' => 'grey']);
        $secret = Str::random(48);

        try {
            $r = $this->client->createWebhook($url, $secret, self::EVENTS);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Could not reach Grey. Nothing was saved. Try again shortly.'];
        }
        if (! $r->successful()) {
            return ['ok' => false, 'message' => 'Grey refused the webhook: '.((string) (data_get($r->json(), 'message') ?: data_get($r->json(), 'error') ?: 'HTTP '.$r->status())).' Nothing was saved.'];
        }

        ProviderKeys::save(['grey_webhook_secret' => $secret]);
        Auditor::log('grey.webhook_registered', 'Provider', null, ['url' => $url, 'events' => self::EVENTS, 'forced' => $force]);

        return ['ok' => true, 'message' => 'Grey will now tell this platform when each payout finishes. The signing secret was saved for you.'];
    }
}
