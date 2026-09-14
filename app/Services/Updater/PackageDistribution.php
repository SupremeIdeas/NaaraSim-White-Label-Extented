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
 *     tiered one uses a real entitlement ordering (Batch 7) — normal < extended,
 *     inclusive upward, so a richer tier is eligible for everything a cheaper one
 *     is plus its own. See tierAllows() for the fail-closed handling of unknown
 *     tiers and untiered instances.
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
            ->filter(fn (DistributedPackage $p) => $this->isEligible($p, $instance, $currentVersion, $product))
            ->sortByDesc(fn (DistributedPackage $p) => UpdateManifest::isValidVersion($p->version) ? $p->version : '')
            ->values();
    }

    /**
     * Is one specific package eligible for this instance at this version? Used by
     * the download endpoint to re-authorise before streaming — never trust that a
     * client only requests what `availableFor` showed it, so this repeats every
     * check `availableFor` made, product line included (Sept-14 audit B1: this
     * used to skip the product check entirely, relying solely on the browse
     * endpoint's filter, so a client that already knew a package_id could
     * download a package built for a different product line outright).
     */
    public function isEligible(DistributedPackage $package, WhiteLabelInstance $instance, string $currentVersion, string $product): bool
    {
        if (! $package->is_published) {
            return false;
        }

        if ($package->product !== $product) {
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

    /**
     * Tier entitlement (Batch 7 — the real ordering that replaced Batch 4's
     * exact-match stopgap). Tiers are ranked (WhiteLabelInstance::TIERS: normal <
     * extended) and entitlement is INCLUSIVE UPWARD — a richer tier gets everything
     * a cheaper one does plus its own, so an extended instance is eligible for both
     * normal-tier and extended-tier packages, while a normal instance is not
     * eligible for extended-only ones. An untiered package (null/empty requirement)
     * is available to everyone, tier or not.
     *
     * Fail-closed on anything unrecognised: a package whose tier_requirement isn't a
     * known tier can only match by exact string (never widened by the ordering), and
     * an instance with no tier is denied any tiered package. Entitlement gating is a
     * paid boundary — an unknown value must never accidentally grant more.
     */
    private function tierAllows(DistributedPackage $package, WhiteLabelInstance $instance): bool
    {
        $required = $package->tier_requirement;

        // Untiered package → everyone.
        if ($required === null || $required === '') {
            return true;
        }

        $requiredRank = WhiteLabelInstance::rankOf($required);
        $instanceRank = $instance->tierRank();

        // Unknown required tier (not in the ordering): fall back to strict exact
        // match — never let an unrecognised value be satisfied by "a higher rank".
        if ($requiredRank === null) {
            return $instance->tier !== null && $instance->tier === $required;
        }

        // Known required tier: the instance must have a known tier ranked at least
        // as high. No tier → no tiered entitlement.
        return $instanceRank !== null && $instanceRank >= $requiredRank;
    }
}
