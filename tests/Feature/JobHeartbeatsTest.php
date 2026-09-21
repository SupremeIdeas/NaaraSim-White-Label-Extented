<?php

namespace Tests\Feature;

use App\Models\JobHeartbeat;
use App\Support\JobHeartbeats;
use App\Support\SchedulerHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 4 #10 Phase B1 — the real heartbeat log every scheduled command writes
 * to on start/completion. This is now the single source of truth
 * SchedulerHealth's overdue math (and the System Health hero) reads from —
 * no job's health is computed a second, different way anywhere else.
 */
class JobHeartbeatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_finished_command_writes_a_success_row_with_duration(): void
    {
        JobHeartbeats::starting("'/usr/bin/php' 'artisan' providers:health-check");
        usleep(1000);
        JobHeartbeats::finished("'/usr/bin/php' 'artisan' providers:health-check");

        $row = JobHeartbeat::where('job_name', 'providers:health-check')->firstOrFail();
        $this->assertSame('success', $row->outcome);
        $this->assertNotNull($row->duration_ms);
        $this->assertGreaterThan(0, $row->duration_ms);
    }

    public function test_a_failed_command_writes_a_failed_row_with_the_exception_message(): void
    {
        JobHeartbeats::failed('esim:sync', new \RuntimeException('provider timeout'));

        $row = JobHeartbeat::where('job_name', 'esim:sync')->firstOrFail();
        $this->assertSame('failed', $row->outcome);
        $this->assertSame('provider timeout', $row->detail);
    }

    public function test_a_skipped_command_writes_a_skipped_row(): void
    {
        JobHeartbeats::skipped('esim:sync-usage');

        $row = JobHeartbeat::where('job_name', 'esim:sync-usage')->firstOrFail();
        $this->assertSame('skipped', $row->outcome);
    }

    /**
     * A command can attach a rich "proof of work" note (e.g. esim:sync
     * enumerating which providers synced vs. skipped) that gets consumed by
     * its own NEXT heartbeat row — never required, purely additive.
     */
    public function test_a_note_is_attached_to_the_next_heartbeat_for_that_job(): void
    {
        JobHeartbeats::note('esim:sync', 'esimgo synced; 6 skipped (not configured).');
        JobHeartbeats::finished('esim:sync');

        $row = JobHeartbeat::where('job_name', 'esim:sync')->firstOrFail();
        $this->assertSame('esimgo synced; 6 skipped (not configured).', $row->detail);

        // Consumed — a second heartbeat with no new note carries no stale detail.
        JobHeartbeats::finished('esim:sync');
        $second = JobHeartbeat::where('job_name', 'esim:sync')->latest('id')->first();
        $this->assertNull($second->detail);
    }

    public function test_last_success_at_ignores_failed_and_skipped_attempts(): void
    {
        JobHeartbeats::failed('nci:recompute', new \RuntimeException('boom'));
        $this->assertNull(JobHeartbeats::lastSuccessAt('nci:recompute'));

        JobHeartbeats::finished('nci:recompute');
        $this->assertNotNull(JobHeartbeats::lastSuccessAt('nci:recompute'));

        // A later failure doesn't erase the earlier success, but the LATEST
        // ATTEMPT (used for display) is still the failure.
        JobHeartbeats::failed('nci:recompute', new \RuntimeException('boom again'));
        $this->assertNotNull(JobHeartbeats::lastSuccessAt('nci:recompute'));
        $this->assertSame('failed', JobHeartbeats::latest('nci:recompute')->outcome);
    }

    public function test_recent_returns_newest_first_across_every_job(): void
    {
        JobHeartbeats::finished('backup:run');
        JobHeartbeats::finished('fx:sync');

        $recent = JobHeartbeats::recent(5);
        $this->assertSame('fx:sync', $recent->first()->job_name);
    }

    /** SchedulerHealth is now a thin cadence-registry facade over JobHeartbeats — no second health check. */
    public function test_scheduler_health_record_and_last_run_delegate_to_job_heartbeats(): void
    {
        SchedulerHealth::record("'/usr/bin/php' 'artisan' backup:clean");

        $this->assertNotNull(SchedulerHealth::lastRun('backup:clean'));
        $this->assertTrue(SchedulerHealth::lastRun('backup:clean')->eq(JobHeartbeats::lastSuccessAt('backup:clean')));
    }
}
