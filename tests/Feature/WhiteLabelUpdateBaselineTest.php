<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Updater\UpdateApplier;
use App\Services\Updater\WhiteLabelUpdateClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A freshly activated copy checks for updates from the version it shipped as (no manual first update needed). */
class WhiteLabelUpdateBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['updater.original_platform_base_url' => 'https://master.test', 'updater.api_token' => '1|abc']);
        Http::fake([
            'master.test/api/v1/white-label/updates/check*' => Http::response(['packages' => []]),
            'master.test/api/v1/white-label/entitlement' => Http::response(['level' => 'full', 'locks' => [], 'skins' => ['allowance' => 5]]),
        ]);
    }

    public function test_a_copy_that_never_applied_an_update_checks_from_its_shipped_version_and_remembers_it(): void
    {
        config(['updater.shipped_version' => '2026.10.13-1']);
        $this->assertNull(Setting::getValue(UpdateApplier::VERSION_SETTING));

        $out = app(WhiteLabelUpdateClient::class)->checkForUpdates();

        $this->assertTrue($out['ok'], (string) ($out['error'] ?? ''));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'current_version=2026.10.13-1'));
        $this->assertSame('2026.10.13-1', Setting::getValue(UpdateApplier::VERSION_SETTING));
    }

    public function test_an_applied_update_always_wins_over_the_shipped_version(): void
    {
        config(['updater.shipped_version' => '2026.10.13-1']);
        Setting::setValue(UpdateApplier::VERSION_SETTING, '2026.11.02-3');

        app(WhiteLabelUpdateClient::class)->checkForUpdates();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'current_version=2026.11.02-3'));
    }

    public function test_a_malformed_shipped_version_is_not_invented_into_a_baseline(): void
    {
        config(['updater.shipped_version' => 'dev-main']);

        $out = app(WhiteLabelUpdateClient::class)->checkForUpdates();

        $this->assertFalse($out['ok']);
        $this->assertNull(Setting::getValue(UpdateApplier::VERSION_SETTING));
    }
}
