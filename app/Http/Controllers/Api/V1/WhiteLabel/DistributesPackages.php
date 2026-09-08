<?php

namespace App\Http\Controllers\Api\V1\WhiteLabel;

use App\Models\DistributedPackage;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\PackageDistribution;
use App\Services\Updater\PackagePublisher;
use App\Support\UpdateManifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared check/download logic for the white-label distribution API (Batch 4 §3).
 * The update and theme controllers are structurally identical — same scopes-based
 * gating, same version/tier eligibility, same activity logging (in middleware) —
 * differing only in which package family they serve, so that difference is the
 * one thing each controller supplies via family().
 */
trait DistributesPackages
{
    /** 'code' or 'theme' — the package family this controller serves. */
    abstract protected function family(): string;

    /**
     * List what's available — metadata only, never the package content. Keeps
     * "checking for updates" a cheap, frequent-safe call; downloading is a
     * separate, deliberate second call.
     */
    public function check(Request $request, PackageDistribution $distribution): JsonResponse
    {
        $instance = $this->instance($request);

        $validated = $request->validate([
            'current_version' => ['required', 'string'],
            'product' => ['required', 'string', 'max:100'],
        ]);

        if (! UpdateManifest::isValidVersion($validated['current_version'])) {
            return response()->json(['message' => 'current_version must be in YYYY.MM.DD-N form.'], 422);
        }

        // Record the version the instance reports having (Batch 4 §1).
        $instance->forceFill(['current_platform_version' => $validated['current_version']])->saveQuietly();

        $packages = $distribution
            ->availableFor($instance, $this->family(), $validated['current_version'], $validated['product'])
            ->map(fn (DistributedPackage $p) => [
                'package_id' => $p->package_id,
                'version' => $p->version,
                'package_type' => $p->package_type,
                'min_compatible_version' => $p->min_compatible_version,
                'changelog' => $p->changelog,
                'size_bytes' => $p->size_bytes,
            ])
            ->all();

        return response()->json(['packages' => $packages]);
    }

    /**
     * Stream one specific already-published package the instance is eligible for.
     * Re-validates tier/version eligibility server-side again — never trust that
     * a client only requests what `check` showed it. The receiving instance is
     * still expected to run PackageVerifier::verify() locally before applying:
     * the signature check happens on the receiving end regardless of the
     * authenticated channel (defence in depth — never trust a network channel
     * alone for integrity).
     */
    public function download(Request $request, string $package, PackageDistribution $distribution): StreamedResponse|JsonResponse
    {
        $instance = $this->instance($request);

        $row = DistributedPackage::where('package_id', $package)->first();

        // Unknown / unpublished / wrong family → 404 (don't leak which of these).
        if ($row === null
            || ! $row->is_published
            || ! in_array($row->package_type, $this->family() === 'theme' ? [DistributedPackage::THEME_TYPE] : DistributedPackage::CODE_TYPES, true)) {
            return response()->json(['message' => 'Package not found.'], 404);
        }

        $currentVersion = (string) $request->query('current_version', $instance->current_platform_version ?? '');
        if (! $distribution->isEligible($row, $instance, $currentVersion)) {
            return response()->json(['message' => 'This instance is not eligible for that package.'], 403);
        }

        if (! Storage::disk(PackagePublisher::DISK)->exists($row->storage_path)) {
            return response()->json(['message' => 'Package file is no longer available.'], 410);
        }

        return Storage::disk(PackagePublisher::DISK)->download(
            $row->storage_path,
            "update-{$row->version}.naaraupdate",
            ['Content-Type' => 'application/octet-stream'],
        );
    }

    private function instance(Request $request): WhiteLabelInstance
    {
        /** @var WhiteLabelInstance $instance */
        $instance = $request->user();

        return $instance;
    }
}
