<?php

namespace Tests\Feature;

use App\Livewire\Admin\SystemHealth;
use App\Models\JobHeartbeat;
use App\Models\User;
use App\Support\CronPulse;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CronPulseTest extends TestCase
{
    use RefreshDatabase;

    private function beat(string $job, \DateTimeInterface $at, string $outcome = 'success'): void
    {
        JobHeartbeat::create(['job_name' => $job, 'started_at' => $at, 'finished_at' => $at, 'duration_ms' => 5, 'outcome' => $outcome]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
    }

    public function test_a_fresh_install_is_amber_not_red_until_the_first_cron_run(): void
    {
        $v = CronPulse::verdict();
        $this->assertSame('waiting', $v['state']);
        $this->assertSame([], $v['issues']);
    }

    public function test_it_turns_green_the_moment_the_cron_pulses_even_though_slow_tasks_never_ran(): void
    {
        $this->beat(CronPulse::JOB, now());
        $v = CronPulse::verdict();
        $this->assertSame('live', $v['state'], 'weekly and monthly tasks that have not had their turn must not keep it red');
        $this->assertGreaterThan(10, $v['never_ran']);
    }

    public function test_it_goes_red_when_the_pulse_stops(): void
    {
        $this->beat(CronPulse::JOB, now()->subMinutes(12));
        $v = CronPulse::verdict();
        $this->assertSame('attention', $v['state']);
        $this->assertSame('cron_stopped', $v['issues'][0]['key']);
    }

    public function test_a_task_that_used_to_run_and_is_now_overdue_is_an_alarm(): void
    {
        $this->beat(CronPulse::JOB, now());
        $this->beat('payouts:guard-sweep', now()->subHours(3));
        $v = CronPulse::verdict();
        $this->assertSame('attention', $v['state']);
        $this->assertContains('Payout Guardian sweeper', $v['overdue']);
    }

    public function test_a_sync_queue_is_flagged_even_with_a_live_cron(): void
    {
        config(['queue.default' => 'sync']);
        $this->beat(CronPulse::JOB, now());
        $this->assertSame('attention', CronPulse::verdict()['state']);
    }

    public function test_the_page_shows_the_banner_for_each_state_and_the_cron_line_while_waiting(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertSee('Waiting for your first cron run')->assertSeeHtml('data-cron-line');

        $this->beat(CronPulse::JOB, now());
        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertSee('All systems are running smoothly')->assertDontSeeHtml('data-cron-line');

        JobHeartbeat::query()->delete();
        $this->beat(CronPulse::JOB, now()->subMinutes(20));
        Livewire::actingAs($admin)->test(SystemHealth::class)->assertSee('Attention required')->assertSee('The cron stopped');
    }

    public function test_the_cron_environment_is_recorded_once_and_compared_with_the_website(): void
    {
        CronPulse::recordEnvironment();
        $env = CronPulse::cronEnvironment();
        $this->assertSame(realpath(base_path()), $env['path']);
        $this->assertSame(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, $env['php_version']);

        $this->beat(CronPulse::JOB, now());
        $this->assertSame('live', CronPulse::verdict()['state'], 'the same folder and the same PHP is healthy');

        \App\Models\Setting::setValue('ops.cron_env', json_encode(['php_binary' => '/usr/bin/php8.1', 'php_version' => '8.1', 'sapi' => 'cli', 'path' => '/home/old/copy-of-the-app', 'user' => 'x', 'seen_at' => now()->toDateTimeString()]), 'ops');
        $keys = array_column(CronPulse::verdict()['issues'], 'key');
        $this->assertContains('cron_wrong_path', $keys, 'a cron on an old copy of the app must be named');
        $this->assertContains('cron_php_mismatch', $keys, 'a cron on another PHP version must be named');
    }

    public function test_the_page_shows_the_detected_cron_command_in_both_forms_and_the_php_it_found(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertSeeHtml('data-cron-help')->assertSee('detected on this server')->assertSee(realpath(base_path()))
            ->assertSee('schedule:run')->assertSee('cPanel or Cloudways cron form');
    }

    public function test_the_php_binary_for_the_cron_is_a_real_cli_and_never_fpm_or_cgi(): void
    {
        $bin = \App\Support\Installer::phpBinary();
        $this->assertDoesNotMatchRegularExpression('/fpm|apache|cgi|httpd/i', basename($bin));
        $this->assertTrue($bin === 'php' || (is_file($bin) && is_executable($bin)), 'a detected path must exist and run: '.$bin);
        $this->assertStringContainsString(realpath(base_path()), \App\Support\Installer::cronLine());
        $this->assertStringStartsWith('cd '.realpath(base_path()).' && ', \App\Support\Installer::cronCommandOnly());
    }
}
