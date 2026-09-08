<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Gigs (Connectivity API) — a Full-eSIM provider for the Naara Connect line: a
 * full MVNO stack (voice, SMS, data + a real number) provisioned onto an eSIM.
 *
 * Key-gated ("Coming Soon" until credentials are saved). Auth is a Bearer API
 * key; every resource is scoped to a Gigs project, so a project id is configured
 * alongside the key. Plans are provisioned via a subscription against a plan.
 *
 * NOTE: the paths follow the documented Gigs API shape (developers.gigs.com);
 * confirm against the sandbox once access is granted (CatalogueSync SyncStatus
 * surfaces any 4xx). Money-safety: catalogue cost is WHOLESALE and PRIVATE;
 * retail is always ours via the PricingEngine.
 */
class GigsService implements EsimProviderInterface
{
    private function project(): string
    {
        return (string) config('services.gigs.project');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.gigs.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withToken(config('services.gigs.api_key'))
            ->acceptJson();
    }

    public function getCatalogue(): array
    {
        return $this->client()->get("/projects/{$this->project()}/plans", ['limit' => 500])
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        // A Gigs subscription provisions the plan onto a (e)SIM. Idempotent on
        // our reference so a retry never double-provisions.
        return $this->client()->timeout(15)->post("/projects/{$this->project()}/subscriptions", array_filter([
            'plan' => $planId,
            'iccid' => $iccid,
            'metadata' => ['reference' => 'naara-'.Str::uuid()->toString()],
        ]))->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/projects/{$this->project()}/sims/{$iccid}")
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/projects/{$this->project()}/sims/{$iccid}/usageRecords")
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        // $iccid carries the subscription id for Gigs (what an order stores).
        return $this->client()->post("/projects/{$this->project()}/subscriptions/{$iccid}/cancel")
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        // Gigs bills the connected account; no prepaid wallet balance endpoint.
        return 0.0;
    }
}
