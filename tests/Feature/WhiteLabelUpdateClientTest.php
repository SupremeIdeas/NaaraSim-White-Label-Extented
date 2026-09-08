<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Updater\PackageBuilder;
use App\Services\Updater\UpdateApplier;
use App\Services\Updater\WhiteLabelUpdateClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Batch 5 §2/§6 — the white-label subscriber's API client. Proves it never
 * throws into the UI on a check failure, that a download is verified locally
 * before it is ever handed back (a bad/tampered download is rejected exactly
 * like a corrupted manual upload), that a dropped connection never leaves a
 * partial file for the applier to find, and that reportOutcome() never lets a
 * failed report call retroactively fail an update that already applied.
 */
class WhiteLabelUpdateClientTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'test-token');

        Setting::setValue(UpdateApplier::VERSION_SETTING, '2026.09.05-1');
    }

    private function client(): WhiteLabelUpdateClient
    {
        return app(WhiteLabelUpdateClient::class);
    }

    // --- checkForUpdates: never throws, always a clean result shape ---

    public function test_check_for_updates_returns_the_packages_list_on_success(): void
    {
        Http::fake(['master.example.test/*' => Http::response([
            'packages' => [['package_id' => 'p1', 'version' => '2026.09.10-1', 'changelog' => 'fix']],
        ])]);

        $result = $this->client()->checkForUpdates('code');

        $this->assertTrue($result['ok']);
        $this->assertSame('p1', $result['packages'][0]['package_id']);
        $this->assertNull($result['error']);
    }

    public function test_check_for_updates_fails_cleanly_when_not_configured(): void
    {
        config()->set('updater.api_token', null);

        $result = $this->client()->checkForUpdates('code');

        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['packages']);
        $this->assertNotNull($result['error']);
    }

    public function test_check_for_updates_fails_cleanly_with_no_current_version_recorded(): void
    {
        Setting::setValue(UpdateApplier::VERSION_SETTING, null);
        Http::fake(); // no request should even be made

        $result = $this->client()->checkForUpdates('code');

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }

    public function test_check_for_updates_fails_cleanly_when_the_api_is_disabled_upstream(): void
    {
        Http::fake(['master.example.test/*' => Http::response(['message' => 'Not Found'], 404)]);

        $result = $this->client()->checkForUpdates('code');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not enabled', $result['error']);
    }

    public function test_check_for_updates_fails_cleanly_on_a_connection_error(): void
    {
        Http::fake(['master.example.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $result = $this->client()->checkForUpdates('code');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Could not reach', $result['error']);
    }

    // --- download: verifies locally before ever returning a path ---

    private function buildValidPackage(): string
    {
        $dir = storage_path('app/testing/client-'.uniqid());
        File::ensureDirectoryExists($dir);

        return app(PackageBuilder::class)->build([
            'output_dir' => $dir,
            'private_key' => $this->keys['private'],
            'files' => [['path' => 'app/Foo.php', 'action' => 'add', 'content' => '<?php // ok']],
        ]);
    }

    public function test_download_verifies_and_returns_a_local_path_for_a_genuine_package(): void
    {
        $built = $this->buildValidPackage();
        Http::fake(['master.example.test/*' => Http::response(File::get($built), 200)]);

        $path = $this->client()->download('pkg-1', 'code');

        $this->assertFileExists($path);
        $this->assertStringContainsString('update-incoming', $path);
    }

    public function test_download_rejects_and_deletes_a_tampered_package(): void
    {
        $built = $this->buildValidPackage();

        // Corrupt the bytes the "server" sends back.
        $tampered = substr(File::get($built), 0, -20).str_repeat('X', 20);
        Http::fake(['master.example.test/*' => Http::response($tampered, 200)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/local verification/');

        $this->client()->download('pkg-1', 'code');
    }

    public function test_download_throws_cleanly_on_a_non_successful_response(): void
    {
        Http::fake(['master.example.test/*' => Http::response(['message' => 'not eligible'], 403)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/rejected/');

        $this->client()->download('pkg-1', 'code');
    }

    public function test_a_dropped_connection_mid_download_leaves_no_partial_file(): void
    {
        Http::fake(['master.example.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('connection reset')]);

        $before = collect(Storage::disk('local')->allFiles('update-incoming'))->count();

        try {
            $this->client()->download('pkg-1', 'code');
            $this->fail('expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('lost mid-download', $e->getMessage());
        }

        $after = collect(Storage::disk('local')->allFiles('update-incoming'))->count();
        $this->assertSame($before, $after, 'no partial file should have been written');
    }

    // --- reportOutcome: fire-and-forget-ish, never throws ---

    public function test_report_outcome_succeeds_silently(): void
    {
        Http::fake(['master.example.test/*' => Http::response(['message' => 'Recorded.'])]);

        $this->client()->reportOutcome('pkg-1', 'applied', ['downtime_seconds' => 5]);

        Http::assertSent(fn ($request) => $request->url() === 'https://master.example.test/api/v1/white-label/updates/report'
            && $request['package_id'] === 'pkg-1'
            && $request['status'] === 'applied');
    }

    public function test_report_outcome_never_throws_when_the_call_fails(): void
    {
        Log::spy();
        Http::fake(['master.example.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);

        // Must not throw.
        $this->client()->reportOutcome('pkg-1', 'rolled_back');

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_report_outcome_is_a_silent_no_op_when_not_configured(): void
    {
        config()->set('updater.api_token', null);
        Http::fake();

        $this->client()->reportOutcome('pkg-1', 'applied');

        Http::assertNothingSent();
    }
}
