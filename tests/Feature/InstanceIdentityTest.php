<?php

namespace Tests\Feature;

use App\Support\InstanceIdentity;
use App\Support\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Two installs must never share one database, cache, session or queue name. */
class InstanceIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Installer::$envPath = storage_path('framework/testing/.env.identity-test');
        @unlink(Installer::$envPath);
    }

    protected function tearDown(): void
    {
        @unlink(Installer::$envPath);
        Installer::$envPath = null;
        parent::tearDown();
    }

    public function test_every_install_gets_its_own_names_for_everything_that_could_be_shared(): void
    {
        $a = InstanceIdentity::envFor('aaaaaaaaaaaaaaaa');
        $b = InstanceIdentity::envFor('bbbbbbbbbbbbbbbb');
        foreach (['CACHE_PREFIX', 'REDIS_PREFIX', 'HORIZON_PREFIX', 'SESSION_COOKIE', 'INSTANCE_ID'] as $k) {
            $this->assertNotSame($a[$k], $b[$k], $k.' must differ between installs');
        }
        $this->assertNotSame(InstanceIdentity::newId(), InstanceIdentity::newId());
    }

    public function test_a_database_with_tables_and_no_matching_identity_is_foreign(): void
    {
        config(['app.instance_id' => 'mine0000mine0000']);
        DB::table('instance_identity')->delete();
        $this->assertSame('foreign', InstanceIdentity::databaseState());

        DB::table('instance_identity')->insert(['instance_id' => 'theirs00theirs00', 'edition' => 'whitelabel', 'app_key_hash' => 'x', 'db_fingerprint' => 'y', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('foreign', InstanceIdentity::databaseState());
        $this->assertStringContainsString('DIFFERENT install', (string) InstanceIdentity::mismatch());

        DB::table('instance_identity')->delete();
        InstanceIdentity::register('mine0000mine0000');
        $this->assertSame('ours', InstanceIdentity::databaseState());
        $this->assertNull(InstanceIdentity::mismatch());
    }

    public function test_the_installer_refuses_a_database_that_already_holds_another_installs_data_and_writes_nothing(): void
    {
        Installer::unlock();
        config(['installer.enforce_empty_db' => true, 'app.instance_id' => null]);
        DB::table('instance_identity')->delete();

        $this->post('/install/setup', [
            'app_name' => 'Rehav', 'app_url' => 'https://rehav.online', 'hosting_type' => 'shared', 'db_connection' => 'sqlite', 'db_database' => ':memory:',
        ])->assertSessionHasErrors('db_database');

        $this->assertFileDoesNotExist(Installer::$envPath, 'nothing may be written when the database is not ours');
        Installer::markInstalled();
    }

    public function test_adopt_gives_an_older_install_its_identity_and_never_takes_over_someone_elses_database(): void
    {
        DB::table('instance_identity')->delete();
        config(['app.instance_id' => null]);
        $this->artisan('whitelabel:doctor', ['--adopt' => true])->assertExitCode(0);
        $this->assertNotNull(InstanceIdentity::row());
        $this->assertStringContainsString('INSTANCE_ID=', (string) file_get_contents(Installer::$envPath));

        DB::table('instance_identity')->update(['instance_id' => 'someoneelse00000']);
        config(['app.instance_id' => 'mine0000mine0000']);
        $this->artisan('whitelabel:doctor', ['--adopt' => true])->assertExitCode(1);
    }

    public function test_the_doctor_reports_a_foreign_database_as_a_failure_and_prints_no_secret(): void
    {
        config(['app.instance_id' => 'mine0000mine0000']);
        DB::table('instance_identity')->delete();
        DB::table('instance_identity')->insert(['instance_id' => 'theirs00theirs00', 'edition' => 'whitelabel', 'app_key_hash' => 'x', 'db_fingerprint' => 'y', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('whitelabel:doctor')->expectsOutputToContain('DIFFERENT install')->assertExitCode(1);

        ob_start();
        \Illuminate\Support\Facades\Artisan::call('whitelabel:doctor');
        $out = \Illuminate\Support\Facades\Artisan::output().ob_get_clean();
        $this->assertStringNotContainsString((string) config('app.key'), $out);
        $pw = (string) config('database.connections.mysql.password');
        if ($pw !== '') {
            $this->assertStringNotContainsString($pw, $out);
        }
    }
}
