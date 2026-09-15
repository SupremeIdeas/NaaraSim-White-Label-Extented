<?php

namespace App\Jobs;

use App\Models\AppBuild;
use App\Support\AppExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fire the external CI/build-service compile for a build request (App Export §1
 * — every external call is a queued job, never synchronous).
 *
 * Owner audit (2026-09-15) — root-cause fix: this job used to leave an
 * unconfigured build silently "queued" forever "for a self-hosted runner to
 * pick up", but no such runner was ever built anywhere in this codebase — a
 * build with nowhere to compile just sat there, indistinguishable from a slow
 * one. It now FAILS LOUD instead (a clear `failed` status + log line), and a
 * real, concrete CI provider (Codemagic's REST API, verified against
 * docs.codemagic.io/rest-api/builds/) is wired in as a first-class option
 * alongside the original generic/custom-webhook path.
 */
class TriggerAppBuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public int $buildId) {}

    public function handle(): void
    {
        $build = AppBuild::find($this->buildId);
        if (! $build || $build->isTerminal()) {
            return;
        }

        // Android and iOS use DIFFERENT CI (owner audit, 2026-09-15 — Doc B
        // Stage 1): Android always builds via this repo's own pre-built
        // android-build.yml on GitHub Actions (cheap, no macOS runner needed);
        // Codemagic/generic-webhook is an iOS-only choice. Never cross lanes —
        // same "capability routing, not blind failover" discipline the
        // number-provider lanes use.
        if ($build->platform === 'android') {
            $this->triggerGithubActions($build);
        } elseif ((string) AppExport::get('ci_provider', 'generic') === 'codemagic') {
            $this->triggerCodemagic($build);
        } else {
            $this->triggerGeneric($build);
        }
    }

    /**
     * Android CI (docs/APP-EXPORT.md): fires this repo's own
     * .github/workflows/android-build.yml via GitHub's repository_dispatch
     * API — a signed APK+AAB build on a standard Linux runner, far cheaper
     * than a macOS CI provider and exactly what that workflow already expects.
     * The workflow reports back to the SAME signed `/webhooks/appbuild/{provider}`
     * endpoint the generic/Codemagic paths use, so AppBuildWebhookController
     * never needed to change (Doc A Part A item 3).
     */
    private function triggerGithubActions(AppBuild $build): void
    {
        $token = trim((string) config('services.appexport.github_token', ''));
        $repo = trim((string) AppExport::get('github_repo'));

        if ($token === '' || $repo === '') {
            $this->failNoProvider($build, 'GitHub Actions is not configured — set the App Export GitHub token (Admin → API Keys) and the github_repo (owner/repo) in App Builder before generating an Android build.');

            return;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])
            ->timeout(20)
            ->post("https://api.github.com/repos/{$repo}/dispatches", [
                'event_type' => 'app-build',
                'client_payload' => [
                    'build_id' => (string) $build->id,
                    'version' => $build->version,
                    'build_number' => (string) $build->build_number,
                    'callback_url' => route('webhooks.appbuild', ['provider' => 'github']),
                    // Live App Studio config so an admin's settings actually take
                    // effect in the compiled build, not whatever's stale in the
                    // checked-in capacitor.config.json (owner audit gap).
                    'app_config' => \App\Support\AppStudio::medianConfig(),
                    'offline_html' => \App\Support\AppStudio::offlineHtml(),
                ],
            ]);

        // repository_dispatch returns 204 No Content with no body on success —
        // there is no build id to store, unlike Codemagic; the workflow's own
        // callback carries our build_id back, which is all applyStatus() needs.
        if (! $response->successful()) {
            $this->failTriggerRejected($build, $response, 'GitHub Actions');

            return;
        }

        $build->update([
            'status' => AppBuild::STATUS_BUILDING,
            'external_ref' => "github_actions:{$repo}@".(string) AppExport::get('github_branch', 'main'),
        ]);
    }

    /** The original provider-agnostic iOS path: a signed webhook to any custom macOS-CI receiver. */
    private function triggerGeneric(AppBuild $build): void
    {
        $webhook = trim((string) AppExport::get('ci_webhook_url'));
        if ($webhook === '') {
            $this->failNoProvider($build, 'No iOS CI provider configured — set a CI webhook URL, or switch to Codemagic and configure it, in App Builder before generating an iOS build.');

            return;
        }

        $secret = (string) config('services.appexport.ci_secret', '');
        $payload = [
            'build_id' => $build->id,
            'platform' => $build->platform,
            'artifact_type' => $build->artifact_type,
            'version' => $build->version,
            'build_number' => $build->build_number,
            'callback_url' => route('webhooks.appbuild', ['provider' => 'ci']),
            // The full native config in the real Median appConfig.json shape — so
            // the compile backend has everything it needs (App Studio, BUILD-21).
            'app_config' => \App\Support\AppStudio::medianConfig(),
            'offline_html' => \App\Support\AppStudio::offlineHtml(),
        ];
        $body = json_encode($payload);
        $signature = $secret !== '' ? hash_hmac('sha256', $body, $secret) : '';

        $response = Http::withHeaders(['X-Naara-Signature' => $signature])
            ->timeout(20)
            ->withBody($body, 'application/json')
            ->post($webhook);

        if (! $response->successful()) {
            $this->failTriggerRejected($build, $response, 'CI webhook');

            return;
        }

        $build->update([
            'status' => AppBuild::STATUS_BUILDING,
            'external_ref' => $response->json('build_ref') ?? $build->external_ref,
        ]);
    }

    /**
     * Codemagic's real REST API (owner audit, 2026-09-15 — verified against
     * docs.codemagic.io/rest-api/builds/): POST /builds with appId/workflowId/
     * branch + environment.variables, auth via the x-auth-token header. The
     * response's `buildId` is stored in `external_ref` (the column already
     * existed for exactly this, just never populated). Codemagic reports
     * completion via a post-publish script in the operator's own codemagic.yaml
     * — not a webhook it calls automatically — so the operator wires that
     * script to POST back to AppBuildWebhookController themselves (see the
     * Codemagic integration reference doc); this job only needs to trigger.
     */
    private function triggerCodemagic(AppBuild $build): void
    {
        $token = trim((string) config('services.appexport.codemagic_api_token', ''));
        $appId = trim((string) AppExport::get('codemagic_app_id'));
        $workflowId = trim((string) AppExport::get('codemagic_ios_workflow_id'));

        if ($token === '' || $appId === '' || $workflowId === '') {
            $this->failNoProvider($build, 'Codemagic is not fully configured — set the API token (Admin → API Keys), app id, and iOS workflow id in App Builder before generating an iOS build.');

            return;
        }

        $response = Http::withHeaders(['x-auth-token' => $token])
            ->timeout(20)
            ->post('https://api.codemagic.io/builds', [
                'appId' => $appId,
                'workflowId' => $workflowId,
                'branch' => (string) AppExport::get('codemagic_branch', 'main'),
                'environment' => [
                    'variables' => [
                        'NAARA_BUILD_ID' => (string) $build->id,
                        'NAARA_PLATFORM' => $build->platform,
                        'NAARA_ARTIFACT_TYPE' => $build->artifact_type,
                        'NAARA_VERSION' => $build->version,
                        'NAARA_BUILD_NUMBER' => (string) $build->build_number,
                        'NAARA_CALLBACK_URL' => route('webhooks.appbuild', ['provider' => 'codemagic']),
                        // Live App Studio config, same reasoning as the generic/
                        // GitHub Actions paths — codemagic.yaml's "Apply live app
                        // config" step patches capacitor.config.json from this
                        // before `cap add ios` copies it into the native project.
                        'NAARA_APP_CONFIG' => json_encode(\App\Support\AppStudio::medianConfig()),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $this->failTriggerRejected($build, $response, 'Codemagic');

            return;
        }

        $build->update([
            'status' => AppBuild::STATUS_BUILDING,
            'external_ref' => $response->json('buildId') ?? $build->external_ref,
        ]);
    }

    private function failNoProvider(AppBuild $build, string $message): void
    {
        $build->update(['status' => AppBuild::STATUS_FAILED, 'log' => $message]);
    }

    private function failTriggerRejected(AppBuild $build, Response $response, string $providerLabel): void
    {
        $build->update([
            'status' => AppBuild::STATUS_FAILED,
            'log' => "{$providerLabel} rejected the build trigger: HTTP {$response->status()} — ".Str::limit($response->body(), 500),
        ]);
    }

    /**
     * All 3 queued attempts threw (e.g. the CI provider was unreachable every
     * time) — mark the build Failed rather than leaving it stuck at "queued"
     * indefinitely, the exact silent-failure mode this job was rewritten to
     * eliminate. Money-safety rule 7 (never blind-retry) is why this only
     * fires after the queue's own bounded retry budget is exhausted, not on
     * the first transient error.
     */
    public function failed(\Throwable $exception): void
    {
        $build = AppBuild::find($this->buildId);
        if ($build && ! $build->isTerminal()) {
            $build->update([
                'status' => AppBuild::STATUS_FAILED,
                'log' => 'Could not reach the CI provider after 3 attempts: '.$exception->getMessage(),
            ]);
        }
    }
}
