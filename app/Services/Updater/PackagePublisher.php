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

        $row = DistributedPackage::updateOrCreate(
            ['package_id' => $manifest->packageId],
            [
                'product' => $manifest->product,
                'version' => $manifest->version,
                'package_type' => $manifest->packageType,
                'min_compatible_version' => $manifest->minCompatibleVersion,
                'tier_requirement' => $manifest->tierRequirement,
                'changelog' => $manifest->changelog,
                'storage_path' => $storagePath,
                'size_bytes' => (int) (Storage::disk(self::DISK)->size($storagePath) ?: filesize($packagePath)),
                'is_published' => $publish,
            ],
        );

        Auditor::log($publish ? 'distribution.published' : 'distribution.registered', DistributedPackage::class, $row->id, [
            'package_id' => $manifest->packageId,
            'version' => $manifest->version,
            'product' => $manifest->product,
        ]);

        return $row;
    }

    /** Flip an already-registered package's published state on or off. */
    public function setPublished(DistributedPackage $package, bool $published, ?int $actorId = null): void
    {
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
