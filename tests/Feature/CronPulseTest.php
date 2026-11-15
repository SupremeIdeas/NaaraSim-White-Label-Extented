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
}
