<?php

namespace Tests\Feature;

use App\Livewire\Admin\SystemHealth;
use App\Models\AuditLog;
use App\Models\JobHeartbeat;
use App\Models\User;
use App\Support\HostingGuide;
use App\Support\PendingMigrations;
use App\Support\QueueHealth;
use App\Support\SchedulerHealth;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HOTFIX §2 — the System Health diagnostic. Each scheduled task records its own
 * last run; the panel flags a task as overdue (cron not firing) and surfaces the
 * queue connection so "payment didn't credit / health widget empty" becomes a
 * visible cron/queue problem instead of a re-debug of correct code.
 */
class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_it_extracts_the_command_name_from_a_scheduler_string(): void
    {
        $this->assertSame('providers:health-check',
            SchedulerHealth::commandName("'/usr/bin/php' 'artisan' providers:health-check"));
        $this->assertSame('queue:work',
            SchedulerHealth::commandName('php artisan queue:work --stop-when-empty --tries=1'));
        $this->assertSame('esim:sync', SchedulerHealth::commandName('esim:sync'));
    }

    public function test_a_recorded_task_is_not_overdue_but_an_unrun_task_is(): void
    {
        SchedulerHealth::record("'/usr/bin/php' 'artisan' providers:health-check");

        $report = collect(SchedulerHealth::report())->keyBy('name');

        $this->assertFalse($report['providers:health-check']['overdue']); // just ran
        $this->assertNotNull($report['providers:health-check']['last_run']);
        $this->assertTrue($report['esim:sync']['overdue']); // never run this test
        $this->assertTrue(SchedulerHealth::anyOverdue());
    }

    public function test_the_page_is_admin_only_and_renders(): void
    {
        Livewire::actingAs(User::factory()->create())->test(SystemHealth::class)->assertStatus(403);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Provider health check');
    }

    public function test_the_page_shows_the_worker_layer_and_cache_controls(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Worker driver')
            ->assertSee('Horizon')
            ->assertSee('Failed jobs')
            ->assertSee('Clear app cache');
    }

    public function test_an_admin_can_flush_the_cache_and_a_user_cannot(): void
    {
        Cache::put('probe', 'x', 60);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->call('flushCache', 'app')
            ->assertDispatched('nx-toast');

        // A non-admin is refused at mount already (page is admin-only).
        Livewire::actingAs(User::factory()->create())->test(SystemHealth::class)->assertStatus(403);
    }

    public function test_the_worker_verdict_flags_a_sync_queue_as_degraded(): void
    {
        // The test env runs QUEUE_CONNECTION=sync, so jobs are not backgrounded.
        $verdict = QueueHealth::workerVerdict();
        $this->assertFalse($verdict['healthy']);
        $this->assertStringContainsString('inline', strtolower((string) $verdict['reason']));
    }

    public function test_horizon_active_treats_a_paused_master_as_not_processing(): void
    {
        // Redis reachable + a master supervisor present is NOT enough: a paused
        // Horizon queues jobs and drains nothing. The health probe must call that
        // out, the VPS equivalent of a cron that isn't firing.
        $repo = \Mockery::mock(MasterSupervisorRepository::class);
        $repo->shouldReceive('all')->andReturn([(object) ['name' => 'master-1', 'status' => 'paused']]);
        $this->app->instance(MasterSupervisorRepository::class, $repo);

        $this->assertFalse(QueueHealth::horizonActive());
    }

    public function test_horizon_active_is_true_for_a_running_master(): void
    {
        $repo = \Mockery::mock(MasterSupervisorRepository::class);
        $repo->shouldReceive('all')->andReturn([(object) ['name' => 'master-1', 'status' => 'running']]);
        $this->app->instance(MasterSupervisorRepository::class, $repo);

        $this->assertTrue(QueueHealth::horizonActive());
    }

    public function test_horizon_active_is_false_when_no_master_is_running(): void
    {
        $repo = \Mockery::mock(MasterSupervisorRepository::class);
        $repo->shouldReceive('all')->andReturn([]);
        $this->app->instance(MasterSupervisorRepository::class, $repo);

        $this->assertFalse(QueueHealth::horizonActive());
    }

    public function test_the_hosting_guide_gives_ordered_steps_for_both_modes(): void
    {
        $steps = HostingGuide::steps();
        $this->assertArrayHasKey('shared', $steps);
        $this->assertArrayHasKey('vps', $steps);
        // The cron line carries the real app path + a schedule:run.
        $cron = HostingGuide::cronLine();
        $this->assertStringContainsString('artisan schedule:run', $cron);
        $this->assertStringContainsString(base_path(), $cron);
        // Shared drives the queue from cron; VPS runs Horizon.
        $this->assertStringContainsString('database', json_encode($steps['shared']));
        $this->assertStringContainsString('horizon', strtolower(json_encode($steps['vps'])));
    }

    /**
     * Every Schedule::command(...) entry in routes/console.php must have a
     * matching SchedulerHealth::TASKS row, or the cron silently stopping for
     * that job would never be flagged on the System Health page — exactly the
     * "provider health widget is empty" class of bug this feature exists to
     * catch. Audit found esim:sync-usage, esim:prune-usage-snapshots,
     * payouts:earnings-run, staff:compensation-close and
     * brand-subscriptions:bill were scheduled but unmonitored — now fixed.
     */
    public function test_every_scheduled_command_is_monitored(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));
        preg_match_all("/Schedule::command\('([a-z0-9:_-]+)/i", $consoleRoutes, $matches);
        $scheduled = array_unique($matches[1]);
        $this->assertNotEmpty($scheduled);

        foreach ($scheduled as $command) {
            $this->assertArrayHasKey($command, SchedulerHealth::TASKS,
                "Scheduled command '{$command}' has no SchedulerHealth::TASKS entry — it would silently stop with no admin alert.");
        }
    }

    public function test_the_page_renders_the_hosting_setup_guide(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Hosting &amp; background setup', false)
            ->assertSee('schedule:run');
    }

    /**
     * No-terminal migration runner (owner request, 2026-09-08: shared-cPanel
     * installs often have no SSH access at all, and a VPS/Cloudways install
     * saves an SSH round trip either way, so a code update shipping new
     * migrations needed a click-to-run path). PendingMigrations reuses
     * Laravel's own Migrator/repository resolution — it can never drift
     * from what `php artisan migrate` would really do.
     */
    public function test_pending_migrations_reports_none_on_a_freshly_migrated_database(): void
    {
        $this->assertSame([], PendingMigrations::names());
        $this->assertSame(0, PendingMigrations::count());
    }

    public function test_the_database_updates_panel_shows_up_to_date_for_a_super_admin(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        Livewire::actingAs($superAdmin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Database updates')
            ->assertSee('Up to date')
            ->assertSee('No pending migrations');
    }

    public function test_a_plain_admin_sees_the_panel_but_not_the_run_button(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Database updates')
            ->assertSee('Only a super admin can run database updates')
            ->assertDontSee('wire:click="runMigrations"', false);
    }

    public function test_a_plain_admin_cannot_call_run_migrations_directly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->call('runMigrations')
            ->assertStatus(403);
    }

    /**
     * Tier 4 #10 Phase B2.4 fix — nciHealth() used to compute its OWN
     * hardcoded "overdue after 26h" window for nci:recompute (a daily job),
     * which never matched what SchedulerHealth::report() already computes
     * for that exact task (expected 86400s -> threshold ~114912s, ~31.9h).
     * At 27h since the last run — overdue under the OLD 26h constant, but
     * NOT overdue under the real ~31.9h threshold — the page must now agree
     * with SchedulerHealth's own math, proving the duplicate check is gone.
     */
    public function test_nci_health_no_longer_uses_its_own_hardcoded_26_hour_window(): void
    {
        JobHeartbeat::create([
            'job_name' => 'nci:recompute',
            'started_at' => now()->subHours(27)->subMinute(),
            'finished_at' => now()->subHours(27),
            'duration_ms' => 500,
            'outcome' => 'success',
        ]);

        $report = collect(SchedulerHealth::report())->firstWhere('name', 'nci:recompute');
        $this->assertFalse($report['overdue']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertViewHas('nci', fn ($nci) => $nci['recompute_overdue'] === false);
    }

    /** Tier 4 #10 Phase B2 — three real states on the scheduled-tasks list, not two. */
    public function test_the_scheduled_tasks_list_shows_a_neutral_state_for_a_skipped_run(): void
    {
        JobHeartbeat::create([
            'job_name' => 'esim:sync',
            'started_at' => now(),
            'finished_at' => now(),
            'outcome' => 'skipped',
            'detail' => 'All 6 provider(s) skipped — not configured.',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Not configured')
            ->assertSee('All 6 provider(s) skipped — not configured.');
    }

    /** Tier 4 #10 Phase B2.1 — a live, timestamped feed of real executions. */
    public function test_the_recent_activity_feed_shows_real_heartbeats(): void
    {
        JobHeartbeat::create([
            'job_name' => 'esim:sync',
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 1234,
            'outcome' => 'success',
            'detail' => 'esimgo synced; 6 skipped (not configured).',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Recent activity')
            ->assertSee('esim:sync')
            ->assertSee('esimgo synced; 6 skipped (not configured).')
            ->assertSee('1234ms');
    }

    public function test_a_super_admin_running_migrations_with_nothing_pending_is_a_safe_no_op(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        Livewire::actingAs($superAdmin)->test(SystemHealth::class)
            ->call('runMigrations')
            ->assertOk()
            ->assertDispatched('nx-toast');

        // A no-op run must never write an audit row — nothing actually happened.
        $this->assertSame(0, AuditLog::where('action', 'admin.migrations_run')->count());
    }
}
