<?php

namespace App\Services\AppExport;

use App\Jobs\TriggerAppBuildJob;
use App\Models\AppBuild;
use App\Models\User;
use App\Support\AppExport;
use App\Support\Auditor;

/**
 * Turns a "Generate Build" click into a real build record and (if a CI provider
 * is configured) fires the external compile generically — a signed webhook the
 * provider calls back to report status. Kept provider-agnostic (§1: "swapping
 * providers later doesn't require rearchitecting") so Android CI, Codemagic,
 * Capawesome etc. all plug into the same trigger + status-polling contract.
 */
class BuildDispatcher
{
    public function create(string $platform, string $artifactType, ?User $user = null): AppBuild
    {
        abort_unless(in_array($platform, ['android', 'ios'], true), 422);
        abort_unless(in_array($artifactType, ['apk', 'aab', 'ipa'], true), 422);

        $build = AppBuild::create([
            'platform' => $platform,
            'artifact_type' => $artifactType,
            'version' => (string) AppExport::get('version', '1.0.0'),
            'build_number' => (int) AppExport::get('build_number', 1),
            'status' => AppBuild::STATUS_QUEUED,
            'release_notes' => (string) AppExport::get('changelog', ''),
            'triggered_by' => $user?->id,
        ]);

        Auditor::log('appexport.build_requested', AppBuild::class, $build->id, [
            'platform' => $platform, 'artifact' => $artifactType, 'version' => $build->version,
        ]);

        // Fire the external compile out-of-band (never in the request cycle).
        TriggerAppBuildJob::dispatch($build->id);

        return $build;
    }

    /** Apply a status update from the CI/build-service callback. Idempotent-ish. */
    public function applyStatus(AppBuild $build, string $status, ?string $artifactUrl = null, ?string $log = null): void
    {
        if ($build->isTerminal()) {
            return; // never resurrect a finished build
        }

        $build->status = $status;
        if ($artifactUrl !== null) {
            $build->artifact_url = $artifactUrl;
        }
        if ($log !== null) {
            $build->log = $log;
        }
        $build->save();

        // A ready standalone APK becomes the public download target.
        if ($status === AppBuild::STATUS_READY && $build->artifact_type === 'apk' && $build->artifact_url) {
            AppExport::save(['android_apk_url' => $build->artifact_url]);
        }

        Auditor::log('appexport.build_status', AppBuild::class, $build->id, ['status' => $status]);
    }
}
