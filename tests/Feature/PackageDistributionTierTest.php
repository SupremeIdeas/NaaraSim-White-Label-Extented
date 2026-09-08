<?php

namespace Tests\Feature;

use App\Models\DistributedPackage;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\PackageDistribution;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Batch 7 — the real tier entitlement ORDERING that replaced Batch 4's exact-match
 * stopgap. Entitlement is inclusive upward (normal < extended): a richer tier is
 * eligible for everything a cheaper one is, plus its own; a cheaper tier is not
 * eligible for a richer tier's packages. Untiered packages reach everyone. The
 * matrix here is the whole point of the batch, so it's exhaustive — and it fails
 * CLOSED on unknown tiers and untiered instances (a paid boundary must never widen
 * by accident). Pure eligibility logic, so no DB/HTTP — just the two models.
 */
class PackageDistributionTierTest extends TestCase
{
    private function eligible(?string $instanceTier, ?string $packageTierRequirement): bool
    {
        $instance = new WhiteLabelInstance(['tier' => $instanceTier, 'status' => WhiteLabelInstance::ACTIVE]);

        $package = new DistributedPackage([
            'version' => '2026.09.10-1',
            'min_compatible_version' => '2026.09.01-1',
            'tier_requirement' => $packageTierRequirement,
            'is_published' => true,
        ]);
        // is_published is only assignable via the cast when persisted; force it on
        // the in-memory instance so isEligible()'s published gate passes.
        $package->is_published = true;

        return app(PackageDistribution::class)->isEligible($package, $instance, '2026.09.05-1');
    }

    /** @return array<string, array{?string, ?string, bool}> */
    public static function tierMatrix(): array
    {
        return [
            // instance tier, package requirement, expected eligibility
            'untiered pkg reaches untiered instance' => [null, null, true],
            'untiered pkg reaches normal instance' => ['normal', null, true],
            'untiered pkg reaches extended instance' => ['extended', null, true],

            'normal pkg reaches normal instance' => ['normal', 'normal', true],
            'normal pkg reaches extended instance (upward inclusion)' => ['extended', 'normal', true],
            'normal pkg denied to untiered instance' => [null, 'normal', false],

            'extended pkg reaches extended instance' => ['extended', 'extended', true],
            'extended pkg denied to normal instance' => ['normal', 'extended', false],
            'extended pkg denied to untiered instance' => [null, 'extended', false],

            // Fail-closed on an unrecognised requirement: exact match only.
            'unknown pkg tier matches only its exact instance tier' => ['enterprise', 'enterprise', true],
            'unknown pkg tier denied to a real tier' => ['extended', 'enterprise', false],
            'unknown pkg tier denied to untiered instance' => [null, 'enterprise', false],
        ];
    }

    #[DataProvider('tierMatrix')]
    public function test_tier_entitlement_matrix(?string $instanceTier, ?string $packageRequirement, bool $expected): void
    {
        $this->assertSame($expected, $this->eligible($instanceTier, $packageRequirement));
    }

    public function test_the_ordering_is_defined_cheapest_to_richest(): void
    {
        // The whole batch hinges on this order; assert it explicitly so a future
        // reorder of the constant can't silently invert entitlement.
        $this->assertSame(['normal', 'extended'], WhiteLabelInstance::TIERS);
        $this->assertTrue(WhiteLabelInstance::rankOf('extended') > WhiteLabelInstance::rankOf('normal'));
        $this->assertNull(WhiteLabelInstance::rankOf(null));
        $this->assertNull(WhiteLabelInstance::rankOf('nonsense'));
    }
}
