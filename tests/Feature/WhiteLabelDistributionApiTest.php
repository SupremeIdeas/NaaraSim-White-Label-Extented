<?php

namespace Tests\Feature;

use App\Models\DistributedPackage;
use App\Models\Setting;
use App\Models\WhiteLabelApiLog;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\PackageBuilder;
use App\Services\Updater\PackagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Batch 4 — the white-label distribution API. Proves the access-control surface:
 * the API is invisible until enabled, only active instances with the right scope
 * reach each action, only newer + compatible + tier-eligible packages are ever
 * offered (re-checked on download, never trusting the client), and every
 * authenticated call — success or failure — leaves exactly one oversight log row.
 */
class WhiteLabelDistributionApiTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    private string $pkgDir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        $this->pkgDir = storage_path('app/testing/dist-'.uniqid());
        File::ensureDirectoryExists($this->pkgDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pkgDir);
        parent::tearDown();
    }

    private function enable(): void
    {
        Setting::setValue('white_label_api.enabled', true);
    }

    private function makeInstance(string $status = WhiteLabelInstance::ACTIVE, ?string $tier = null): WhiteLabelInstance
    {
        return WhiteLabelInstance::create([
            'brand_name' => 'Test Brand '.uniqid(),
            'slug' => 'brand-'.uniqid(),
            'contact_email' => 'brand@example.com',
            'status' => $status,
            'tier' => $tier,
        ]);
    }

    /** @param  list<string>  $scopes */
    private function token(WhiteLabelInstance $instance, array $scopes = WhiteLabelInstance::SCOPES): string
    {
        return $instance->createToken('instance', $scopes)->plainTextToken;
    }

    private function publish(array $overrides = [], bool $publish = true): DistributedPackage
    {
        $type = $overrides['package_type'] ?? 'code_and_migrations';
        $content = $type === 'theme'
            ? [['path' => 'theme.json', 'action' => 'add', 'content' => '{"slug":"x","name":"X","icon_family":{"style":"3d","set":"default"}}']]
            : [['path' => 'app/Foo.php', 'action' => 'add', 'content' => '<?php // '.uniqid()]];

        $path = app(PackageBuilder::class)->build(array_merge([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'product' => 'naarasim-whitelabel',
            'package_type' => $type,
            'version' => '2026.09.10-1',
            'min_compatible_version' => '2026.09.01-1',
            'changelog' => 'test package',
            'files' => $content,
        ], array_diff_key($overrides, ['package_type' => true])));

        return app(PackagePublisher::class)->register($path, publish: $publish);
    }

    // --- Feature flag ---

    public function test_the_api_is_hidden_with_a_404_until_enabled(): void
    {
        $token = $this->token($this->makeInstance());

        $this->withToken($token)
            ->getJson('/api/v1/white-label/updates/check?current_version=2026.09.05-1&product=naarasim-whitelabel')
            ->assertNotFound();

        // A 404 from the disabled-flag gate is NOT an authenticated call → not logged.
        $this->assertSame(0, WhiteLabelApiLog::count());
    }

    // --- Scope enforcement (reusing ApiScope unchanged) ---

    public function test_a_check_only_token_cannot_reach_download(): void
    {
        $this->enable();
        $pkg = $this->publish();
        $instance = $this->makeInstance();
        $token = $this->token($instance, ['updates.check']);

        $this->withToken($token)
            ->getJson('/api/v1/white-label/updates/check?current_version=2026.09.05-1&product=naarasim-whitelabel')
            ->assertOk();

        $this->withToken($token)
            ->getJson("/api/v1/white-label/updates/{$pkg->package_id}/download?current_version=2026.09.05-1")
            ->assertForbidden();
    }

    // --- Instance must be active ---

    public function test_a_suspended_instance_is_rejected(): void
    {
        $this->enable();
        $token = $this->token($this->makeInstance(WhiteLabelInstance::SUSPENDED));

        $this->withToken($token)
            ->getJson('/api/v1/white-label/updates/check?current_version=2026.09.05-1&product=naarasim-whitelabel')
            ->assertForbidden();
    }

    // --- Version / compatibility filtering ---

    public function test_check_only_lists_newer_and_compatible_packages(): void
    {
        $this->enable();
        $current = '2026.09.05-1';

        $this->publish(['version' => '2026.09.01-1', 'min_compatible_version' => '2026.09.01-1']); // older → excluded
        $eligible = $this->publish(['version' => '2026.09.10-1', 'min_compatible_version' => '2026.09.01-1']); // newer + compatible → in
        $this->publish(['version' => '2026.09.20-1', 'min_compatible_version' => '2026.09.15-1']); // newer but min-compat too high → excluded

        $res = $this->withToken($this->token($this->makeInstance()))
            ->getJson("/api/v1/white-label/updates/check?current_version={$current}&product=naarasim-whitelabel")
            ->assertOk();

        $ids = collect($res->json('packages'))->pluck('package_id')->all();
        $this->assertSame([$eligible->package_id], $ids);
    }

    public function test_check_requires_a_valid_current_version(): void
    {
        $this->enable();
        $this->withToken($this->token($this->makeInstance()))
            ->getJson('/api/v1/white-label/updates/check?current_version=not-a-version&product=naarasim-whitelabel')
            ->assertStatus(422);
    }

    // --- Tier filtering ---
    // NOTE: each of these is its own test so it makes exactly ONE authenticated
    // request. Laravel's auth guard caches the resolved user across multiple
    // requests within a single test method, so two different instances hitting
    // the API in one method would both resolve the first — a test-harness
    // artifact only (each real HTTP request is its own process). One identity
    // per test keeps the tier assertions honest.

    public function test_an_untiered_instance_sees_only_untiered_packages(): void
    {
        $this->enable();
        $current = '2026.09.05-1';
        $untiered = $this->publish(['version' => '2026.09.10-1']);
        $this->publish(['version' => '2026.09.11-1', 'tier_requirement' => 'extended']);

        $res = $this->withToken($this->token($this->makeInstance(tier: null)))
            ->getJson("/api/v1/white-label/updates/check?current_version={$current}&product=naarasim-whitelabel")
            ->assertOk();

        $this->assertEqualsCanonicalizing([$untiered->package_id], collect($res->json('packages'))->pluck('package_id')->all());
    }

    public function test_a_matching_tier_instance_also_sees_the_tiered_package(): void
    {
        $this->enable();
        $current = '2026.09.05-1';
        $untiered = $this->publish(['version' => '2026.09.10-1']);
        $tiered = $this->publish(['version' => '2026.09.11-1', 'tier_requirement' => 'extended']);

        $res = $this->withToken($this->token($this->makeInstance(tier: 'extended')))
            ->getJson("/api/v1/white-label/updates/check?current_version={$current}&product=naarasim-whitelabel")
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$untiered->package_id, $tiered->package_id],
            collect($res->json('packages'))->pluck('package_id')->all()
        );
    }

    // --- Download re-validation + streaming ---

    public function test_download_streams_an_eligible_package(): void
    {
        $this->enable();
        $pkg = $this->publish(['version' => '2026.09.10-1']);

        $this->withToken($this->token($this->makeInstance()))
            ->get("/api/v1/white-label/updates/{$pkg->package_id}/download?current_version=2026.09.05-1")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=update-2026.09.10-1.naaraupdate');
    }

    public function test_download_re_checks_tier_eligibility_and_rejects_ineligible(): void
    {
        $this->enable();
        $tiered = $this->publish(['version' => '2026.09.10-1', 'tier_requirement' => 'extended']);

        // Untiered instance holds the download scope but isn't tier-eligible.
        $this->withToken($this->token($this->makeInstance(tier: null)))
            ->getJson("/api/v1/white-label/updates/{$tiered->package_id}/download?current_version=2026.09.05-1")
            ->assertForbidden();
    }

    public function test_download_of_an_unpublished_or_unknown_package_is_404(): void
    {
        $this->enable();
        $staged = $this->publish(['version' => '2026.09.10-1'], publish: false);
        $token = $this->token($this->makeInstance());

        // An unpublished (staged) package.
        $this->withToken($token)
            ->getJson("/api/v1/white-label/updates/{$staged->package_id}/download?current_version=2026.09.05-1")
            ->assertNotFound();

        // A package_id that doesn't exist at all.
        $this->withToken($token)
            ->getJson('/api/v1/white-label/updates/does-not-exist/download?current_version=2026.09.05-1')
            ->assertNotFound();
    }

    // --- Theme endpoints only serve theme packages ---

    public function test_theme_check_only_lists_theme_packages(): void
    {
        $this->enable();
        $this->publish(['version' => '2026.09.10-1', 'package_type' => 'code_and_migrations']);
        $theme = $this->publish(['version' => '2026.09.10-1', 'package_type' => 'theme']);

        $res = $this->withToken($this->token($this->makeInstance()))
            ->getJson('/api/v1/white-label/themes/check?current_version=2026.09.05-1&product=naarasim-whitelabel')
            ->assertOk();

        $this->assertSame([$theme->package_id], collect($res->json('packages'))->pluck('package_id')->all());
    }

    // --- Oversight logging: exactly one row per authenticated call ---

    public function test_every_authenticated_call_logs_exactly_one_row_with_the_true_status(): void
    {
        $this->enable();
        $pkg = $this->publish(['version' => '2026.09.10-1']);
        $instance = $this->makeInstance();
        $checkOnly = $this->token($instance, ['updates.check']);

        // 1) a successful check
        $this->withToken($checkOnly)
            ->getJson('/api/v1/white-label/updates/check?current_version=2026.09.05-1&product=naarasim-whitelabel')
            ->assertOk();

        // 2) a scope-denied download (403 thrown downstream — must still be logged)
        $this->withToken($checkOnly)
            ->getJson("/api/v1/white-label/updates/{$pkg->package_id}/download?current_version=2026.09.05-1")
            ->assertForbidden();

        $logs = WhiteLabelApiLog::where('white_label_instance_id', $instance->id)->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(200, $logs[0]->response_status);
        $this->assertSame('white-label.updates.check', $logs[0]->endpoint);
        $this->assertSame(403, $logs[1]->response_status);
        $this->assertSame('white-label.updates.download', $logs[1]->endpoint);

        // The check call also recorded the instance's reported version + stamped check-in.
        $this->assertSame('2026.09.05-1', $instance->fresh()->current_platform_version);
        $this->assertNotNull($instance->fresh()->last_checked_in_at);
    }
}
