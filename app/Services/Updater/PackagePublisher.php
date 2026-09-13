<?php

namespace App\Services\Updater;

use App\Models\DistributedPackage;
use App\Support\Auditor;
use Illuminate\Support\Facades\Storage;

/**
 * Registers a built `.naaraupdate` package into the master's distribution store
 * (Batch 4 §4) so white-label instances can discover and download it. Shared by
 * the `update:package --publish` flag and the admin upload-to-publish action —
 * one implementation of "verify, store, record".
 *
 * The package file is copied to the PRIVATE `local` disk under `distribution/`
 * (never a public URL) — downloads only ever happen through the authenticated,
 * scope-gated streaming endpoint, so the file must not be web-reachable on its
 * own.
 *
 * Registering and publishing are separate by design: `register()` records the
 * package as `is_published = false` (built, staged, not yet offered);
 * `setPublished()` flips it on deliberately. `--publish` does both in one go.
 *
 * Master-Only Distribution Lock: a package built with `distribution_scope =
 * 'master_only'` (`update:package --master-only`) can NEVER be published to
 * white label, full stop — this is the enforcement, not just a UI hint.
 * `register()` forces `is_published = false` for such a package regardless of
 * what was requested, and `setPublished(true)` throws outright rather than
 * silently no-op'ing, because a loud rejection is the right failure mode here
 * — a quiet ignore could look like it worked. This governs ONLY whether a
 * fork can ever receive the package; applying it on the master itself
 * (Admin\Updater) is a completely separate code path and is unaffected.
 */
class PackagePublisher
{
    /** Disk-relative directory the distributable files live under. */
    public const STORE_DIR = 'distribution';

    /** The private disk distribution files live on. */
    public const DISK = 'local';

    public function __construct(private readonly PackageVerifier $verifier)
    {
    }

    /**
     * Verify a package, copy it into the distribution store, and upsert its
     * distributed_packages row. Idempotent on package_id: re-registering the
     * same package refreshes its metadata/file in place.
     */
    public function register(string $packagePath, bool $publish = false, ?int $actorId = null): DistributedPackage
    {
        $result = $this->verifier->verify($packagePath);
        if (! $result->passed) {
            throw new \RuntimeException('Refusing to publish an unverified package: '.$result->reason);
        }
        $manifest = $result->manifest;

        $storagePath = self::STORE_DIR.'/'.$manifest->packageId.'.naaraupdate';
        Storage::disk(self::DISK)->put($storagePath, (string) file_get_contents($packagePath));

        // Master-Only Distribution Lock: a master_only manifest can never end
        // up published, no matter what the caller asked for.
        $effectivePublish = $manifest->isMasterOnly() ? false : $publish;

        $row = DistributedPackage::updateOrCreate(
            ['package_id' => $manifest->packageId],
            [
                'product' => $manifest->product,
                'version' => $manifest->version,
                'package_type' => $manifest->packageType,
                'min_compatible_version' => $manifest->minCompatibleVersion,
                'tier_requirement' => $manifest->tierRequirement,
                'distribution_scope' => $manifest->distributionScope,
                'changelog' => $manifest->changelog,
                'storage_path' => $storagePath,
                'size_bytes' => (int) (Storage::disk(self::DISK)->size($storagePath) ?: filesize($packagePath)),
                'is_published' => $effectivePublish,
            ],
        );

        Auditor::log($effectivePublish ? 'distribution.published' : 'distribution.registered', DistributedPackage::class, $row->id, [
            'package_id' => $manifest->packageId,
            'version' => $manifest->version,
            'product' => $manifest->product,
            'distribution_scope' => $manifest->distributionScope,
        ]);

        return $row;
    }

    /**
     * Flip an already-registered package's published state on or off.
     *
     * @throws \RuntimeException  if asked to publish a master-only package —
     *                            a loud, explicit rejection rather than a
     *                            silent no-op, so an admin action never
     *                            looks like it worked when it didn't.
     */
    public function setPublished(DistributedPackage $package, bool $published, ?int $actorId = null): void
    {
        if ($published && $package->isMasterOnly()) {
            throw new \RuntimeException("Package {$package->package_id} is master-only and can never be distributed to white label.");
        }

        $package->update(['is_published' => $published]);

        Auditor::log($published ? 'distribution.published' : 'distribution.unpublished', DistributedPackage::class, $package->id, [
            'package_id' => $package->package_id,
        ]);
    }

    /** Remove a package from distribution entirely, including its stored file. */
    public function withdraw(DistributedPackage $package, ?int $actorId = null): void
    {
        Storage::disk(self::DISK)->delete($package->storage_path);
        $packageId = $package->package_id;
        $id = $package->id;
        $package->delete();

        Auditor::log('distribution.withdrawn', DistributedPackage::class, $id, ['package_id' => $packageId]);
    }
}
