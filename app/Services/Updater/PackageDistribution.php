<?php

namespace App\Services\Updater;

use App\Models\DistributedPackage;
use App\Models\WhiteLabelInstance;
use App\Support\UpdateManifest;
use Illuminate\Support\Collection;

/**
 * The single source of truth for "which published packages is this white-label
 * instance eligible for" (Batch 4 §3.1). Both the check endpoint (to list) and
 * the download endpoint (to re-authorise the one specific package) run through
 * here — never trust that a client only asks for what `check` showed it; the
 * action that actually matters re-checks against the same rules.
 *
 * Eligibility rules, all enforced here at the source (defence in depth — Batch 2
 * enforces the version rule again locally on the receiving end):
 *   - published only (is_published = true) and same product line
 *   - only the requested family (code updates vs themes)
 *   - version strictly newer than the instance's current version
 *   - min_compatible_version <= the instance's current version (never offer a
 *     package the instance can't safely apply yet)
 *   - tier: an untiered package (tier_requirement null) is always eligible; a
 *     tiered one is eligible only when the instance's tier matches it. Batch 7
 *     replaces this exact-match with a real entitlement ordering — for now the
 *     wiring is correct but has little to gate since tiers aren't populated by
 *     a real payment flow yet.
 */
class PackageDistribution
{
    /**
     * All packages the instance may see, newest first.
     *
     * @param  'code'|'theme'  $family
     * @return Collection<int,DistributedPackage>
     */
    public function availableFor(WhiteLabelInstance $instance, string $family, string $currentVersion, string $product): Collection
    {
        $types = $family === 'theme' ? [DistributedPackage::THEME_TYPE] : DistributedPackage::CODE_TYPES;

        return DistributedPackage::query()
            ->where('is_published', true)
            ->where('product', $product)
            ->whereIn('package_type', $types)
            ->get()
            ->filter(fn (DistributedPackage $p) => $this->isEligible($p, $instance, $currentVersion))
            ->sortByDesc(fn (DistributedPackage $p) => UpdateManifest::isValidVersion($p->version) ? $p->version : '')
            ->values();
    }

    /**
     * Is one specific package eligible for this instance at this version? Used by
     * the download endpoint to re-authorise before streaming.
     */
    public function isEligible(DistributedPackage $package, WhiteLabelInstance $instance, string $currentVersion): bool
    {
        if (! $package->is_published) {
            return false;
        }

        if (! UpdateManifest::isValidVersion($currentVersion)
            || ! UpdateManifest::isValidVersion($package->version)
            || ! UpdateManifest::isValidVersion($package->min_compatible_version)) {
            return false;
        }

        // Strictly newer than what the instance already has.
        if (UpdateManifest::compareVersions($package->version, $currentVersion) <= 0) {
            return false;
        }

        // The instance must already be new enough to apply it.
        if (UpdateManifest::compareVersions($package->min_compatible_version, $currentVersion) > 0) {
            return false;
        }

        return $this->tierAllows($package, $instance);
    }

    private function tierAllows(DistributedPackage $package, WhiteLabelInstance $instance): bool
    {
        if ($package->tier_requirement === null || $package->tier_requirement === '') {
            return true;
        }

        // Batch 7 will generalise this to a real tier ordering / entitlement check.
        return $instance->tier !== null && $instance->tier === $package->tier_requirement;
    }
}
