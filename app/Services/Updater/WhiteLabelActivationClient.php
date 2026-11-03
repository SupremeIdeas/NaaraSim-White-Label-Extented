<?php

namespace App\Services\Updater;

use App\Models\Setting;
use App\Support\Activation\LicenseState;
use App\Support\Auditor;
use App\Support\BrandSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Exchanges the licence key the owner was given for this install's API token, once, from the admin screen. The master records the brand, the
 * contact, the version and the domain this site runs on, so its oversight list shows where every activated white label lives. Never throws.
 */
class WhiteLabelActivationClient
{
    /** @return array{ok:bool, error:?string, tier:?string} */
    public function activate(string $licenseKey, string $platformUrl, ?string $contactEmail = null): array
    {
        $key = trim($licenseKey);
        $url = rtrim(trim($platformUrl), '/');
        if ($key === '' || strlen($key) > 64) {
            return $this->fail('Enter the licence key exactly as it was sent to you.');
        }
        if (! LicenseState::validPlatformUrl($url)) {
            return $this->fail('The platform address must be a secure https:// address.');
        }

        try {
            $response = Http::baseUrl($url.'/api/')->timeout(30)->connectTimeout(5)->acceptJson()->asJson()
                ->post('v1/white-label/activate', array_filter([
                    'license_key' => $key,
                    'brand_name' => BrandSettings::name(),
                    'contact_email' => $contactEmail ?: (BrandSettings::profile('support_email') ?: null),
                    'current_version' => $this->version(),
                    'domain' => LicenseState::domain(),
                ], fn ($v) => $v !== null && $v !== ''));
        } catch (ConnectionException $e) {
            return $this->fail('Could not reach the platform at '.$url.'. Check the address and your server\'s outbound connection.');
        }

        if ($response->status() === 404) {
            return $this->fail('The licence service is not switched on at that address right now. Ask the platform operator.');
        }
        if ($response->status() === 429) {
            return $this->fail('Too many attempts. Wait a minute and try again.');
        }
        if ($response->status() === 403) {
            return $this->fail('That key was not accepted. It may be mistyped, revoked, or not active yet. Check it with the platform operator.');
        }
        if ($response->status() === 422) {
            return $this->fail('The platform rejected the details sent: '.collect($response->json('errors') ?? [])->flatten()->first());
        }
        $token = $response->json('token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            return $this->fail('The platform gave an unexpected answer (HTTP '.$response->status().'). Nothing was changed.');
        }

        LicenseState::storeActivation($url, $token, substr($key, -4), (array) $response->json('instance', []));
        Auditor::log('white_label.activated', Setting::class, null, ['platform' => $url, 'tier' => $response->json('instance.tier')]);

        // Pull the feature list straight away so what the licence includes is live the moment activation succeeds.
        $entitlement = app(WhiteLabelUpdateClient::class)->refreshEntitlement();

        return ['ok' => true, 'error' => null, 'tier' => $entitlement['level'] ?? $response->json('instance.tier')];
    }

    private function version(): ?string
    {
        $v = Setting::getValue(UpdateApplier::VERSION_SETTING);

        return is_string($v) && $v !== '' ? $v : (string) config('updater.shipped_version');
    }

    /** @return array{ok:false, error:string, tier:null} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'tier' => null];
    }
}
