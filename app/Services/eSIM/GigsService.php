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
 * Verified against developers.gigs.com (2026-10-18): base https://api.gigs.com (no /v1),
 * projects/{project}/plans|users|subscriptions|sims|usageBalances; confirm the plan `validity` shape and each plan's `requirements`
 * against the sandbox once access is granted (CatalogueSync SyncStatus surfaces any 4xx). Money-safety: catalogue cost is WHOLESALE and PRIVATE;
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

    /**
     * Every eSIM-capable plan, paged by cursor (list endpoints return {object:list, items:[...], moreItemsAfter}; `limit` is 0-200, default
     * only 10). Returned in a shape CatalogueSyncService::mapGigs() reads: ['items' => [...]].
     */
    public function getCatalogue(): array
    {
        $items = [];
        $after = null;
        for ($page = 0; $page < 50; $page++) {
            $res = $this->client()->timeout(30)->get("/projects/{$this->project()}/plans", array_filter(['simType' => 'eSIM', 'limit' => 200, 'after' => $after]))
                ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
            foreach ((array) ($res['items'] ?? []) as $p) {
                $items[] = $p;
            }
            $after = $res['moreItemsAfter'] ?? null;
            if (! $after) {
                break;
            }
        }

        return ['items' => $items];
    }

    /**
     * Provision an eSIM subscription. Gigs needs a USER first (POST /users), then POST /subscriptions {user, plan, sim:"auto"}; the SIM is
     * provisioned asynchronously (the subscription is `pending` with sim null for up to several minutes), after which
     * GET /sims/{sim}/credentials returns the activation code / QR. The user is a synthetic per-order record (no customer PII leaves us).
     * The payload carries `subscriptionId`, and (once ready) iccid / activationCode / qrCodeUrl; `pending` is true until then.
     */
    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $ref = 'naara-'.Str::uuid()->toString();
        $domain = (string) config('services.gigs.user_email_domain', 'users.invalid');

        $user = $this->client()->timeout(15)->post("/projects/{$this->project()}/users", ['email' => "{$ref}@{$domain}", 'fullName' => 'Naara eSIM', 'metadata' => ['reference' => $ref]])
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        $sub = $this->client()->timeout(15)->withHeaders(['Idempotency-Key' => $ref])->post("/projects/{$this->project()}/subscriptions", [
            'user' => $user['id'] ?? null, 'plan' => $planId, 'sim' => $iccid ?: 'auto', 'metadata' => ['reference' => $ref],
        ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        $out = $this->activation((string) ($sub['id'] ?? ''), $sub, (int) config('services.gigs.poll_tries', 5));

        return $out + ['reference' => $ref, 'orderReference' => $out['subscriptionId'] ?: $ref];
    }

    /**
     * Read a subscription back (bounded wait) until its SIM exists, then fetch the install credentials. Safe to call again later by id.
     *
     * @return array<string,mixed>
     */
    public function activation(string $subscriptionId, array $sub = [], int $tries = 1): array
    {
        $creds = [];
        for ($i = 0; $i < max(1, $tries); $i++) {
            if ($i > 0 || $sub === []) {
                $sub = $this->client()->get("/projects/{$this->project()}/subscriptions/{$subscriptionId}")
                    ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
            }
            $simId = is_array($sub['sim'] ?? null) ? ($sub['sim']['id'] ?? null) : ($sub['sim'] ?? null);
            if ($simId) {
                $creds = $this->client()->get("/projects/{$this->project()}/sims/{$simId}/credentials")->json() ?? [];
                if (! empty($creds['activationCode'])) {
                    break;
                }
            }
            if ($i < $tries - 1) {
                usleep((int) config('services.gigs.poll_wait_ms', 3000) * 1000);
            }
        }
        $sim = is_array($sub['sim'] ?? null) ? $sub['sim'] : [];

        return [
            'subscriptionId' => (string) ($sub['id'] ?? $subscriptionId),
            'id' => (string) ($sub['id'] ?? $subscriptionId),
            'status' => (string) ($sub['status'] ?? 'pending'),
            'phoneNumber' => $sub['phoneNumber'] ?? null,
            'iccid' => $sim['iccid'] ?? null,
            'activationCode' => $creds['activationCode'] ?? null,
            'qrCodeUrl' => $creds['qrCodeUrl'] ?? null,
            'iosInstallUrl' => $creds['iosInstallUrl'] ?? null,
            'androidInstallUrl' => $creds['androidInstallUrl'] ?? null,
            'pending' => empty($creds['activationCode']),
        ];
    }

    public function getEsim(string $iccid): array
    {
        // $iccid carries the SIM id (sim_...) or ICCID; the eSIM profile reports the on-device install state.
        return $this->client()->get("/projects/{$this->project()}/sims/{$iccid}/eSimProfile")
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    /** Remaining allowance per subscription: GET /usageBalances?subscription={id} ($iccid carries the subscription id, as in revoke()). */
    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/projects/{$this->project()}/usageBalances", ['subscription' => $iccid])
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        // $iccid carries the subscription id for Gigs (what an order stores). Revoking an undelivered order ends it immediately
        // (POST .../end); a normal cancellation at period end would be POST .../cancel.
        return $this->client()->post("/projects/{$this->project()}/subscriptions/{$iccid}/end")
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        // Gigs bills the connected account; no prepaid wallet balance endpoint.
        return 0.0;
    }
}
