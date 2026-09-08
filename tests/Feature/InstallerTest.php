<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installer;
use App\Support\ProviderStatus;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Installer::unlock();
        Installer::$envPath = storage_path('framework/testing/.env.install-test');
        @unlink(Installer::$envPath);
    }

    protected function tearDown(): void
    {
        @unlink(Installer::$envPath);
        Installer::$envPath = null;
        Installer::markInstalled();
        parent::tearDown();
    }

    public function test_a_fresh_server_redirects_to_the_installer(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
    }

    public function test_welcome_then_requirements_are_reachable(): void
    {
        $this->get('/install')->assertOk()->assertSee('Let’s start', false);
        $this->get('/install/requirements')->assertOk()->assertSee('Server Requirements');
        $this->get('/install/setup')->assertOk()->assertSee('Setup')->assertSee('Environment');
    }

    public function test_installed_app_closes_the_installer(): void
    {
        Installer::markInstalled();
        $this->get('/install')->assertRedirect('/login');
    }

    public function test_bootstrap_key_seeds_a_valid_key_on_a_fresh_upload(): void
    {
        // Fresh upload: not installed, no APP_KEY → the installer would otherwise
        // 500 in the encrypting middleware. bootstrapKey must seed one.
        Installer::unlock();
        config(['app.key' => null]);

        Installer::bootstrapKey();

        $key = config('app.key');
        $this->assertNotEmpty($key);
        $this->assertStringStartsWith('base64:', $key);
        $this->assertSame(32, strlen(base64_decode(substr($key, 7)))); // AES-256 length
        // It also persisted the key to .env so it survives the form round-trip.
        $this->assertStringContainsString('APP_KEY=', file_get_contents(Installer::$envPath));
    }

    public function test_bootstrap_key_is_a_noop_once_keyed_or_installed(): void
    {
        // Already keyed → left untouched.
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        Installer::bootstrapKey();
        $this->assertSame('base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', config('app.key'));

        // Installed but no key → do NOT mask a real ops error by inventing one.
        Installer::markInstalled();
        config(['app.key' => null]);
        Installer::bootstrapKey();
        $this->assertNull(config('app.key'));
    }

    public function test_install_creates_the_default_super_admin_and_shows_the_done_screen(): void
    {
        $response = $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'hosting_type' => 'shared',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ]);

        $response->assertOk()
            ->assertSee('Installation Completed')
            ->assertSee(DefaultAdminSeeder::EMAIL, false)
            ->assertSee(DefaultAdminSeeder::PASSWORD, false);

        $admin = User::where('email', DefaultAdminSeeder::EMAIL)->firstOrFail();
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue(Installer::isInstalled());

        $env = file_get_contents(Installer::$envPath);
        $this->assertStringContainsString('APP_NAME=NaaraSim', $env);
        $this->assertStringContainsString('APP_URL=https://naarasim.test', $env);
    }

    public function test_shared_hosting_writes_database_drivers(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'hosting_type' => 'shared',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertOk();

        $env = file_get_contents(Installer::$envPath);
        $this->assertStringContainsString('CACHE_STORE=database', $env);
        $this->assertStringContainsString('SESSION_DRIVER=database', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $env);
    }

    public function test_vps_hosting_writes_redis_drivers(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'hosting_type' => 'vps',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertOk();

        $env = file_get_contents(Installer::$envPath);
        $this->assertStringContainsString('CACHE_STORE=redis', $env);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $env);
    }

    public function test_hosting_type_is_required(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertSessionHasErrors('hosting_type');

        $this->assertFalse(Installer::isInstalled());
    }

    public function test_app_url_with_a_trailing_slash_is_rejected(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test/', // trailing slash
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertSessionHasErrors('app_url');

        $this->assertFalse(Installer::isInstalled());
    }

    public function test_coming_soon_when_a_provider_key_is_blank(): void
    {
        config(['services.getatext.api_key' => 'sk_live_123', 'services.fivesim.api_key' => null]);

        $this->assertSame('Active', ProviderStatus::label('getatext'));
        $this->assertSame('Coming Soon', ProviderStatus::label('fivesim'));
    }
}
