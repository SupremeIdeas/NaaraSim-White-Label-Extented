<?php

namespace Tests\Feature;

use App\Livewire\Admin\Backups;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\ConfigBackup;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** The portable configuration backup: one sealed file that restores everything configured on a fresh install with a different APP_KEY, minus the critical API keys. */
class ConfigBackupTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');
        $u->forceFill(['two_factor_secret' => encrypt('SECRETKEY'), 'two_factor_confirmed_at' => now()])->save();

        return $u;
    }

    private function configure(): void
    {
        Setting::setValue('brand.tagline', 'Stay Connected. No Borders.', 'brand');
        Setting::setValue('smtp.from', ['name' => 'Naara', 'address' => 'hello@naara.test'], 'mail');
        Setting::setValue('providers.keys', ['services.esimgo.api_key' => 'SUPER-SECRET-PROVIDER-KEY'], 'providers');
        DB::table('posts')->insert(['title' => 'Billing', 'slug' => 'billing', 'body' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** What a brand new install looks like to the code: a different key encrypts every setting. */
    private function becomeFreshInstall(): void
    {
        DB::table('settings')->delete();
        DB::table('posts')->delete();
        $this->app->forgetInstance('encrypter');
        $this->app->singleton('encrypter', fn () => new Encrypter(random_bytes(32), 'AES-256-CBC'));
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_the_file_is_sealed_and_never_holds_the_critical_api_keys(): void
    {
        $this->configure();
        $file = app(ConfigBackup::class)->export('correct horse battery');

        $this->assertStringNotContainsString('SUPER-SECRET-PROVIDER-KEY', $file);
        $this->assertStringNotContainsString('hello@naara.test', $file, 'the contents are encrypted, not just base64');
        $this->assertStringNotContainsString('Stay Connected', $file);
        $this->assertSame('naara-config', json_decode($file, true)['format']);
    }

    public function test_it_restores_on_a_fresh_install_with_a_different_app_key(): void
    {
        $this->configure();
        $file = app(ConfigBackup::class)->export('correct horse battery');
        $this->becomeFreshInstall();
        $this->assertNull(Setting::getValue('brand.tagline'));

        $r = app(ConfigBackup::class)->restore($file, 'correct horse battery');

        $this->assertSame('Stay Connected. No Borders.', Setting::getValue('brand.tagline'));
        $this->assertSame(['name' => 'Naara', 'address' => 'hello@naara.test'], Setting::getValue('smtp.from'));
        $this->assertNull(Setting::getValue('providers.keys'), 'the critical API keys are never restored');
        $this->assertSame('billing', DB::table('posts')->value('slug'));
        $this->assertGreaterThanOrEqual(2, $r['settings']);
        $this->assertSame([], array_intersect(array_keys($r['tables']), ['announcements', 'users']), 'campaign history and users are never part of it');
    }

    public function test_ids_that_point_at_users_are_cleared_not_left_dangling(): void
    {
        $author = User::factory()->create();
        DB::table('posts')->insert(['title' => 'Hello', 'slug' => 'hello', 'body' => 'x', 'author_id' => $author->id, 'created_at' => now(), 'updated_at' => now()]);
        $file = app(ConfigBackup::class)->export('correct horse battery');
        DB::table('posts')->delete();

        app(ConfigBackup::class)->restore($file, 'correct horse battery');

        $this->assertSame('hello', DB::table('posts')->value('slug'));
        $this->assertNull(DB::table('posts')->value('author_id'));
    }

    public function test_a_restore_never_overwrites_this_installs_own_api_keys(): void
    {
        $this->configure();
        $file = app(ConfigBackup::class)->export('correct horse battery');
        Setting::setValue('providers.keys', ['services.esimgo.api_key' => 'KEY-ON-THIS-INSTALL'], 'providers');

        app(ConfigBackup::class)->restore($file, 'correct horse battery');

        $this->assertSame(['services.esimgo.api_key' => 'KEY-ON-THIS-INSTALL'], Setting::getValue('providers.keys'));
    }

    public function test_a_tampered_file_cannot_smuggle_the_api_key_store_in(): void
    {
        $this->configure();
        $cfg = app(ConfigBackup::class);
        $ref = new \ReflectionMethod($cfg, 'seal');
        $ref->setAccessible(true);
        $evil = $ref->invoke($cfg, json_encode(['format' => 'naara-config', 'version' => 1, 'settings' => [['key' => 'providers.keys', 'value' => ['x' => 'y']], ['key' => 'ok.setting', 'value' => 1]], 'tables' => ['users' => [['id' => 99, 'email' => 'x@y.z']]]]), 'correct horse battery');
        Setting::setValue('providers.keys', ['mine' => 'kept'], 'providers');

        $cfg->restore($evil, 'correct horse battery');

        $this->assertSame(['mine' => 'kept'], Setting::getValue('providers.keys'));
        $this->assertSame(1, Setting::getValue('ok.setting'));
        $this->assertSame(0, DB::table('users')->where('email', 'x@y.z')->count(), 'only allow-listed tables are ever written');
    }

    public function test_wrong_passphrase_or_damaged_file_changes_nothing(): void
    {
        $this->configure();
        $file = app(ConfigBackup::class)->export('correct horse battery');
        Setting::setValue('brand.tagline', 'changed since', 'brand');

        foreach ([[$file, 'wrong passphrase!'], [substr($file, 0, -40), 'correct horse battery'], ['not json', 'correct horse battery']] as [$f, $p]) {
            try {
                app(ConfigBackup::class)->restore($f, $p);
                $this->fail('expected a refusal');
            } catch (\RuntimeException) {
                $this->assertSame('changed since', Setting::getValue('brand.tagline'));
            }
        }
    }

    public function test_a_short_passphrase_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);
        app(ConfigBackup::class)->export('short');
    }

    public function test_preview_counts_without_writing(): void
    {
        $this->configure();
        $file = app(ConfigBackup::class)->export('correct horse battery');
        Setting::setValue('brand.tagline', 'different', 'brand');

        $p = app(ConfigBackup::class)->preview($file, 'correct horse battery');

        $this->assertGreaterThanOrEqual(1, $p['settings']['changed']);
        $this->assertSame('different', Setting::getValue('brand.tagline'), 'preview writes nothing');
        $this->assertSame(['providers.keys'], $p['skipped']);
    }

    public function test_the_admin_page_runs_check_then_restore_for_a_super_admin_only(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->configure();
        $file = app(ConfigBackup::class)->export('correct horse battery');
        Setting::setValue('brand.tagline', 'changed since', 'brand');
        $upload = UploadedFile::fake()->createWithContent('c.naaracfg', $file);

        Livewire::actingAs($this->superAdmin())->test(Backups::class)
            ->set('cfgFile', $upload)->set('cfgRestorePassphrase', 'wrong passphrase!')->call('previewConfig')
            ->assertSet('cfgPreview', null)->assertSee('Wrong passphrase')
            ->set('cfgRestorePassphrase', 'correct horse battery')->call('previewConfig')
            ->assertSee('Nothing written yet')->call('restoreConfig')->assertSee('Configuration restored');

        $this->assertSame('Stay Connected. No Borders.', Setting::getValue('brand.tagline'));
        $plain = User::factory()->create();
        $plain->assignRole('admin');
        Livewire::actingAs($plain)->test(Backups::class)->assertForbidden();
    }
}
