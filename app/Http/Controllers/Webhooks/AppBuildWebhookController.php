<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AppBuild;
use App\Services\AppExport\BuildDispatcher;
use Illuminate\Http\Request;

/**
 * CI/build-service status callback (App Export §1). The provider posts back the
 * build outcome; we verify the HMAC signature (rule 9 — verify BEFORE touching
 * the payload) then flip the build's status + attach the artifact URL and log.
 */
class AppBuildWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, BuildDispatcher $dispatcher)
    {
        $secret = (string) config('services.appexport.ci_secret', '');
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Naara-Signature', '');

        // Fails CLOSED (readiness-audit fix, 2026-09-07): this route flips a
        // build to "ready" with an artifact_url that becomes the public app
        // download — an unconfigured secret used to accept any unsigned
        // request, which would let anyone who finds this URL point the
        // official app download at a malicious APK. Configure
        // APPEXPORT_CI_SECRET before wiring up a build runner.
        abort_if($secret === '', 401);
        $expected = hash_hmac('sha256', $raw, $secret);
        abort_unless(hash_equals($expected, $signature), 401);

        $data = $request->json()->all();
        $build = AppBuild::find($data['build_id'] ?? 0);
        abort_unless($build, 404);

        $status = $data['status'] ?? '';
        abort_unless(in_array($status, [
            AppBuild::STATUS_BUILDING, AppBuild::STATUS_READY, AppBuild::STATUS_FAILED,
        ], true), 422);

        $dispatcher->applyStatus(
            $build,
            $status,
            isset($data['artifact_url']) ? (string) $data['artifact_url'] : null,
            isset($data['log']) ? (string) $data['log'] : null,
        );

        return response()->json(['ok' => true]);
    }
}
