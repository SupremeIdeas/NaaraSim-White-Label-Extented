<?php

namespace App\Services\Updater;

use App\Models\Setting;
use App\Support\FeatureEntitlements;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * White-label subscriber's counterpart to Batch 4's distribution API (Batch 5
 * §2). Lives ONLY in the white-label fork — the master platform is the
 * publisher, not a subscriber. Deliberately thin: this is the lightest batch
 * in the roadmap on purpose (§0) — it checks, downloads, verifies with the
 * unmodified Batch 1 `PackageVerifier`, and reports back. All apply/rollback
 * logic stays exactly where Batch 2/3 already built it.
 */
class WhiteLabelUpdateClient
{
    public function __construct(private readonly PackageVerifier $verifier)
    {
    }

    /**
     * List available packages for one family. Never throws into the UI — any
     * failure (missing config, connectivity, a non-2xx response) comes back as
     * a clean error result the admin screen can render, so a subscriber whose
     * network path to the master is down still gets a clear message rather
     * than a crashed page.
     *
     * @param  'code'|'theme'  $family
     * @return array{ok:bool, packages:array<int,array<string,mixed>>, error:?string}
     */
    public function checkForUpdates(string $family = 'code'): array
    {
        $currentVersion = $this->currentVersion();
        if ($currentVersion === null) {
            return $this->failure('No current platform version is recorded yet — apply at least one update manually before checking for updates.');
        }

        $client = $this->client();
        if ($client === null) {
            return $this->failure('This instance is not configured to reach the original platform yet (missing base URL or API token).');
        }

        $segment = $family === 'theme' ? 'themes' : 'updates';

        try {
            $response = $client->get("v1/white-label/{$segment}/check", [
                'current_version' => $currentVersion,
                'product' => config('updater.product_identifier'),
            ]);
        } catch (ConnectionException $e) {
            return $this->failure('Could not reach the original platform: '.$e->getMessage());
        }

        if ($response->status() === 404) {
            return $this->failure('The distribution API is not enabled on the original platform right now.');
        }
        if (! $response->successful()) {
            return $this->failure('The original platform rejected the check: '.($response->json('message') ?? $response->status()));
        }

        // A successful check-in is the natural moment to also refresh this
        // fork's feature entitlement (Batch 8B) — the operator may have raised
        // this instance's level (e.g. basic→standard once paid) since the last
        // check. Fire-and-forget: the lock refresh must never change the result
        // of, or throw out of, an update check. Only on the 'code' check so a
        // paired code+theme poll doesn't double the call.
        if ($family === 'code') {
            $this->refreshEntitlement();
        }

        return ['ok' => true, 'packages' => $response->json('packages') ?? [], 'error' => null];
    }

    /**
     * Fork-only (Batch 8B): re-fetch this instance's feature entitlement from
     * the master and cache the resolved lock list locally, so the universal
     * gates (`App\Support\FeatureEntitlements`) enforce the operator's current
     * decision on this live deployment — not just the copy baked into the last
     * activation response. Exposed as its own action (the admin screen can call
     * it directly) and also run opportunistically on every update check-in.
     *
     * Resilience matches the rest of this client and the Batch 5/8 posture:
     * - Never throws into the UI — a clean {ok, level, locks, error} on any
     *   failure (not configured, connectivity, a non-2xx or unreadable response).
     * - Writes the cache ONLY on a clean 2xx with a well-formed lock list. A
     *   transient failure leaves the last-known list untouched — never wiped
     *   (no accidental unlock) and never invented (no surprise hard-lock).
     *
     * @return array{ok:bool, level:?string, locks:array<int,string>, error:?string}
     */
    public function refreshEntitlement(): array
    {
        $client = $this->client();
        if ($client === null) {
            return $this->entitlementFailure('This instance is not configured to reach the original platform yet (missing base URL or API token).');
        }

        try {
            $response = $client->get('v1/white-label/entitlement');
        } catch (ConnectionException $e) {
            return $this->entitlementFailure('Could not reach the original platform: '.$e->getMessage());
        }

        if ($response->status() === 404) {
            return $this->entitlementFailure('The distribution API is not enabled on the original platform right now.');
        }
        if (! $response->successful()) {
            return $this->entitlementFailure('The original platform rejected the entitlement check: '.($response->json('message') ?? $response->status()));
        }

        $locks = $response->json('locks');
        if (! is_array($locks)) {
            // A malformed payload is a failure like any other: keep the
            // last-known list rather than corrupting the cache with garbage.
            return $this->entitlementFailure('The original platform returned an unreadable entitlement payload.');
        }

        $locks = array_values(array_filter($locks, 'is_string'));
        FeatureEntitlements::store($locks);

        $level = $response->json('level');

        return ['ok' => true, 'level' => is_string($level) ? $level : null, 'locks' => $locks, 'error' => null];
    }

    /**
     * Download and LOCALLY VERIFY one package before ever returning its path —
     * a download that fails verification is treated exactly like a corrupted
     * upload in the manual flow (Batch 2), never silently handed to the
     * applier/installer. Buffers the response fully before writing anything to
     * disk, so a dropped connection mid-transfer throws before a single byte is
     * written — there is no partial file to clean up.
     *
     * @param  'code'|'theme'  $family
     *
     * @throws \RuntimeException on any failure (not configured, connectivity,
     *                           a non-2xx response, or local verification failure)
     */
    public function download(string $packageId, string $family = 'code'): string
    {
        $client = $this->client();
        if ($client === null) {
            throw new \RuntimeException('This instance is not configured to reach the original platform yet.');
        }

        $segment = $family === 'theme' ? 'themes' : 'updates';

        try {
            $response = $client->get("v1/white-label/{$segment}/{$packageId}/download", [
                'current_version' => $this->currentVersion(),
                'product' => config('updater.product_identifier'),
            ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Connection to the original platform was lost mid-download: '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Download rejected by the original platform: '.($response->json('message') ?? $response->status()));
        }

        $relative = 'update-incoming/pull-'.Str::uuid().'.naaraupdate';
        Storage::disk('local')->put($relative, $response->body());
        $absolute = Storage::disk('local')->path($relative);

        $result = $this->verifier->verify($absolute, config('updater.product_identifier'));
        if (! $result->passed) {
            @unlink($absolute);
            throw new \RuntimeException('Downloaded package failed local verification: '.$result->reason);
        }

        return $absolute;
    }

    /**
     * Report what happened when a downloaded package was actually applied
     * (Batch 5 §4) — closes the oversight loop. Fire-and-forget-ish: a failure
     * here is logged locally and swallowed, never allowed to retroactively
     * fail an update that already applied (or already rolled back) correctly.
     *
     * @param  'applied'|'rolled_back'|'failed'  $status
     * @param  array<string,mixed>  $context  e.g. downtime_seconds, applied_at, notes
     */
    public function reportOutcome(string $packageId, string $status, array $context = []): void
    {
        $client = $this->client();
        if ($client === null) {
            Log::warning('white-label update outcome not reported: client not configured', compact('packageId', 'status'));

            return;
        }

        try {
            $client->post('v1/white-label/updates/report', array_merge([
                'package_id' => $packageId,
                'status' => $status,
            ], $context));
        } catch (ConnectionException|RequestException $e) {
            Log::warning('white-label update outcome report failed', [
                'package_id' => $packageId, 'status' => $status, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function currentVersion(): ?string
    {
        $version = Setting::getValue(UpdateApplier::VERSION_SETTING);

        return is_string($version) && $version !== '' ? $version : null;
    }

    /** Null when this instance isn't configured to reach the original platform yet. */
    private function client(): ?PendingRequest
    {
        $baseUrl = config('updater.original_platform_base_url');
        $token = config('updater.api_token');

        if (empty($baseUrl) || empty($token)) {
            return null;
        }

        // Bounded so a slow/dead master can't hang the request cycle — matches
        // the connect/total timeout discipline every other provider client in
        // this codebase already follows (e.g. GetatextService).
        return Http::baseUrl(rtrim($baseUrl, '/').'/api/')
            ->timeout(30)->connectTimeout(5)
            ->withToken($token)
            ->acceptJson();
    }

    /** @return array{ok:bool, packages:array<int,mixed>, error:string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'packages' => [], 'error' => $message];
    }

    /** @return array{ok:bool, level:null, locks:array<int,string>, error:string} */
    private function entitlementFailure(string $message): array
    {
        return ['ok' => false, 'level' => null, 'locks' => [], 'error' => $message];
    }
}
