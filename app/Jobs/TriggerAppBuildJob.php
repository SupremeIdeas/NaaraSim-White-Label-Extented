<?php

namespace App\Jobs;

use App\Models\AppBuild;
use App\Support\AppExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Fire the external CI/build-service compile for a build request (App Export §1
 * — every external call is a queued job, never synchronous). If no CI webhook
 * is configured the build simply stays "queued" for a self-hosted runner to
 * pick up; we never fake a "building"/"ready" state the pipeline hasn't reached.
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

        $webhook = trim((string) AppExport::get('ci_webhook_url'));
        if ($webhook === '') {
            return; // self-hosted runner mode — leave it queued for pickup
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

        $build->update([
            'status' => AppBuild::STATUS_BUILDING,
            'external_ref' => $response->json('build_ref') ?? $build->external_ref,
        ]);
    }
}
