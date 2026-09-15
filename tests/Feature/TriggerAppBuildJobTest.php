<?php

namespace Tests\Feature;

use App\Jobs\TriggerAppBuildJob;
use App\Models\AppBuild;
use App\Support\AppExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * App Export audit (2026-09-15) — the root-cause fix: a build used to sit
 * silently "queued" forever whenever no real CI provider was configured,
 * because the self-hosted runner the code's own comment promised was never
 * built anywhere. This now fails loud (a clear `failed` status + log line)
 * for all three real paths: Android's GitHub Actions dispatch (Doc B Stage 1
 * — Android always builds here, never Codemagic), the original generic/
 * custom-webhook iOS path, and the first-class Codemagic REST API iOS
 * integration (verified against docs.codemagic.io/rest-api/builds/).
 */
class TriggerAppBuildJobTest extends TestCase
{
    use RefreshDatabase;

    private function build(string $platform = 'ios'): AppBuild
    {
        return AppBuild::create([
            'platform' => $platform, 'artifact_type' => $platform === 'ios' ? 'ipa' : 'apk',
            'version' => '1.0.0', 'build_number' => 1, 'status' => AppBuild::STATUS_QUEUED,
        ]);
    }

    /* -------- Android — GitHub Actions (repository_dispatch) -------------- */

    public function test_android_always_routes_through_github_actions_regardless_of_ci_provider(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        // Deliberately set ci_provider=codemagic to prove Android never crosses
        // into the iOS lane, even when that's the active setting.
        AppExport::save(['ci_provider' => 'codemagic', 'github_repo' => 'SupremeIdeas/NaaraSim', 'github_branch' => 'main']);
        config(['services.appexport.github_token' => 'gh-token']);
        $build = $this->build('android');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_BUILDING, $build->status);
        $this->assertSame('github_actions:SupremeIdeas/NaaraSim@main', $build->external_ref);

        Http::assertSent(function ($r) use ($build) {
            return $r->url() === 'https://api.github.com/repos/SupremeIdeas/NaaraSim/dispatches'
                && $r->hasHeader('Authorization', 'Bearer gh-token')
                && $r['event_type'] === 'app-build'
                && $r['client_payload']['build_id'] === (string) $build->id
                && $r['client_payload']['version'] === '1.0.0'
                && array_key_exists('app_config', $r['client_payload'])
                && array_key_exists('offline_html', $r['client_payload']);
        });
    }

    public function test_android_fails_loud_when_github_actions_not_configured(): void
    {
        Http::fake();
        AppExport::save(['github_repo' => '']);
        config(['services.appexport.github_token' => '']);
        $build = $this->build('android');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_FAILED, $build->status);
        $this->assertStringContainsString('GitHub Actions is not configured', $build->log);
        Http::assertNothingSent();
    }

    public function test_android_github_actions_rejection_fails_the_build(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);
        AppExport::save(['github_repo' => 'SupremeIdeas/NaaraSim']);
        config(['services.appexport.github_token' => 'bad-token']);
        $build = $this->build('android');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_FAILED, $build->status);
        $this->assertStringContainsString('GitHub Actions rejected', $build->log);
        $this->assertStringContainsString('401', $build->log);
    }

    /* -------- iOS — generic webhook ---------------------------------------- */

    public function test_an_unconfigured_ios_build_fails_loud_instead_of_staying_queued_forever(): void
    {
        Http::fake();
        AppExport::save(['ci_provider' => 'generic', 'ci_webhook_url' => '']);
        $build = $this->build('ios');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_FAILED, $build->status);
        $this->assertStringContainsString('No iOS CI provider configured', $build->log);
        Http::assertNothingSent();
    }

    public function test_generic_webhook_success_marks_building_and_stores_the_external_ref(): void
    {
        Http::fake(['ci.example.test/*' => Http::response(['build_ref' => 'ext-123'])]);
        AppExport::save(['ci_provider' => 'generic', 'ci_webhook_url' => 'https://ci.example.test/trigger']);
        config(['services.appexport.ci_secret' => 'shh']);
        $build = $this->build('ios');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_BUILDING, $build->status);
        $this->assertSame('ext-123', $build->external_ref);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Naara-Signature') && $r['build_id'] === $build->id);
    }

    public function test_generic_webhook_rejection_fails_the_build_with_the_http_status_in_the_log(): void
    {
        Http::fake(['ci.example.test/*' => Http::response(['error' => 'bad request'], 422)]);
        AppExport::save(['ci_provider' => 'generic', 'ci_webhook_url' => 'https://ci.example.test/trigger']);
        $build = $this->build('ios');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_FAILED, $build->status);
        $this->assertStringContainsString('422', $build->log);
    }

    /* -------- iOS — Codemagic ---------------------------------------------- */

    public function test_codemagic_path_fails_loud_when_not_fully_configured(): void
    {
        Http::fake();
        AppExport::save(['ci_provider' => 'codemagic', 'codemagic_app_id' => '', 'codemagic_ios_workflow_id' => '']);
        config(['services.appexport.codemagic_api_token' => '']);
        $build = $this->build('ios');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_FAILED, $build->status);
        $this->assertStringContainsString('Codemagic is not fully configured', $build->log);
        Http::assertNothingSent();
    }

    public function test_codemagic_trigger_posts_the_real_documented_shape_and_stores_the_build_id(): void
    {
        Http::fake(['api.codemagic.io/builds' => Http::response(['buildId' => '5fabc6414c483700143f4f92'])]);
        config(['services.appexport.codemagic_api_token' => 'cm-token']);
        AppExport::save([
            'ci_provider' => 'codemagic',
            'codemagic_app_id' => 'app-123',
            'codemagic_ios_workflow_id' => 'wf-ios',
            'codemagic_branch' => 'main',
        ]);
        $build = $this->build('ios');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_BUILDING, $build->status);
        $this->assertSame('5fabc6414c483700143f4f92', $build->external_ref);

        Http::assertSent(function ($r) use ($build) {
            return $r->url() === 'https://api.codemagic.io/builds'
                && $r->hasHeader('x-auth-token', 'cm-token')
                && $r['appId'] === 'app-123'
                && $r['workflowId'] === 'wf-ios'
                && $r['branch'] === 'main'
                && $r['environment']['variables']['NAARA_BUILD_ID'] === (string) $build->id
                && $r['environment']['variables']['NAARA_PLATFORM'] === 'ios';
        });
    }

    public function test_codemagic_rejection_fails_the_build(): void
    {
        Http::fake(['api.codemagic.io/builds' => Http::response(['error' => 'invalid token'], 401)]);
        config(['services.appexport.codemagic_api_token' => 'bad-token']);
        AppExport::save(['ci_provider' => 'codemagic', 'codemagic_app_id' => 'app-123', 'codemagic_ios_workflow_id' => 'wf-ios']);
        $build = $this->build('ios');

        (new TriggerAppBuildJob($build->id))->handle();

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_FAILED, $build->status);
        $this->assertStringContainsString('Codemagic rejected', $build->log);
        $this->assertStringContainsString('401', $build->log);
    }

    /* -------- Shared lifecycle behavior ------------------------------------ */

    public function test_a_terminal_build_is_never_re_triggered(): void
    {
        Http::fake();
        $build = $this->build('ios');
        $build->update(['status' => AppBuild::STATUS_READY, 'artifact_url' => 'https://cdn/app.ipa']);

        (new TriggerAppBuildJob($build->id))->handle();

        Http::assertNothingSent();
    }

    public function test_exhausting_all_retries_marks_the_build_failed_instead_of_leaving_it_stuck(): void
    {
        AppExport::save(['ci_provider' => 'generic', 'ci_webhook_url' => 'https://ci.example.test/trigger']);
        $build = $this->build('ios');
        $job = new TriggerAppBuildJob($build->id);

        $job->failed(new \RuntimeException('Connection timed out'));

        $build->refresh();
        $this->assertSame(AppBuild::STATUS_FAILED, $build->status);
        $this->assertStringContainsString('Connection timed out', $build->log);
    }

    public function test_android_and_ios_ci_configured_are_independent(): void
    {
        AppExport::save(['github_repo' => '']);
        config(['services.appexport.github_token' => '']);
        $this->assertFalse(AppExport::androidCiConfigured());

        AppExport::save(['github_repo' => 'SupremeIdeas/NaaraSim']);
        config(['services.appexport.github_token' => 'gh-token']);
        $this->assertTrue(AppExport::androidCiConfigured());

        AppExport::save(['ci_provider' => 'generic', 'ci_webhook_url' => '']);
        $this->assertFalse(AppExport::iosCiConfigured());

        AppExport::save(['ci_webhook_url' => 'https://ci.example.test/trigger']);
        $this->assertTrue(AppExport::iosCiConfigured());

        // Android configured but iOS not — ciConfigured() (either/or) is true,
        // but the platform-specific checks correctly stay apart.
        AppExport::save(['ci_provider' => 'codemagic', 'codemagic_app_id' => '', 'codemagic_ios_workflow_id' => '']);
        config(['services.appexport.codemagic_api_token' => '']);
        $this->assertFalse(AppExport::iosCiConfigured());
        $this->assertTrue(AppExport::ciConfigured());
    }
}
