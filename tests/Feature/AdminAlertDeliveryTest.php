<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Jobs\SendWebPushJob;
use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * BUILD-5 §3 — a critical alert must actually reach an admin promptly, not just
 * sit in error_logs. AlertAdminJob fans out to every admin via web push
 * (instant) and email (guaranteed), throttled per code so a flapping alert
 * can't storm them. The durable error_logs row is always written regardless.
 */
class AdminAlertDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        // Email is the guaranteed channel but only fires once a real mailer is
        // configured (Mailer is best-effort). Turn it on for these tests.
        config(['mail.default' => 'smtp']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_an_alert_writes_the_log_and_notifies_admins(): void
    {
        Queue::fake();
        Notification::fake();
        $admin = $this->admin();
        User::factory()->create(); // a normal user must NOT be notified

        (new AlertAdminJob('payout_failed', 'Payout #1 failed', ['payout_id' => 1]))->handle();

        $this->assertDatabaseHas('error_logs', ['code' => 'payout_failed']);
        Queue::assertPushed(SendWebPushJob::class, fn ($job) => $job->userId === $admin->id);
        Notification::assertSentTo($admin, AdminAlertNotification::class);
        Notification::assertCount(1); // only the admin, not the normal user
    }

    public function test_the_same_alert_code_is_throttled_but_still_logged(): void
    {
        Queue::fake();
        Notification::fake();
        $this->admin();

        (new AlertAdminJob('esimgo_provider_down', 'down'))->handle();
        (new AlertAdminJob('esimgo_provider_down', 'still down'))->handle();

        // Both are logged durably…
        $this->assertSame(2, ErrorLog::where('code', 'esimgo_provider_down')->count());
        // …but the near-real-time fan-out fired only once (cooldown).
        Notification::assertCount(1);
        Queue::assertPushed(SendWebPushJob::class, 1);
    }
}
