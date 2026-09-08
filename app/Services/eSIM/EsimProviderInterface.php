<?php

namespace App\Services\eSIM;

/**
 * The single contract every eSIM provider implements (blueprint Section 5.1),
 * so providers stay swappable and controllers/jobs never touch a concrete
 * provider — they go through ProviderRouter. Bound in the container as
 * esim.esimgo / esim.airalo / esim.quibity.
 */
interface EsimProviderInterface
{
    /** Full provider catalogue (raw), used as the sync source. */
    public function getCatalogue(): array;

    /** Order a bundle; returns the provider's order payload. */
    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array;

    /** Fetch a single eSIM by ICCID. */
    public function getEsim(string $iccid): array;

    /** Real-time usage for a bundle on an eSIM. */
    public function getUsage(string $iccid, string $bundleName): array;

    /** Revoke/cancel a bundle — the refund path for unstarted bundles. */
    public function revoke(string $iccid, string $bundleName): array;

    /** NaaraSim's prepaid wallet balance held with this provider. */
    public function getBalance(): float;
}
