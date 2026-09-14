<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelProjectIntake;
use App\Notifications\WhiteLabelDeploymentReadyNotification;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

/**
 * Prompt 21-EXT2 §6 — the daily sweep command that flips an elapsed
 * `in_progress` intake to `completed`. Same per-row isolation discipline as
 * every other batch command: one broken row never aborts the sweep.
 */
class CheckWhiteLabelDeployTimelinesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function licenses(): WhiteLabelLicenseService
    {
        return app(WhiteLabelLicenseService::class);
    }

    private function intakes(): WhiteLabelProjectIntakeService
    {
        return app(WhiteLabelProjectIntakeService::class);
    }

    private function configureMail(): void
    {
        MailSettings::save([
            'mailer' => 'smtp', 'host' => 'smtp.test', 'port' => 587,
            'username' => 'u', 'password' => 'p',
            'from_address' => 'no-reply@naarasim.test', 'from_name' => 'NaaraSim',
        ]);
    }

    private function inProgressIntake(int $days, ?User $owner = null): WhiteLabelProjectIntake
    {
        $owner ??= User::factory()->create();
        $instance = $this->licenses()->register([
            'brand_name' => 'Acme '.uniqid(),
            'contact_email' => uniqid().'@acme.test',
            'owner_user_id' => $owner->id,
        ]);
        $instance = $this->licenses()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
        $admin = User::factory()->create();

        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);
        $this->intakes()->markSeen($intake, $admin->id);
        $this->intakes()->setDeployTimeline($intake, $days);

        return $intake->refresh();
    }

    public function test_it_completes_an_elapsed_in_progress_intake_and_notifies_the_owner(): void
    {
        $this->configureMail();
        Notification::fake();
        $owner = User::factory()->create();
        $intake = $this->inProgressIntake(5, $owner);

        Carbon::setTestNow(now()->addDays(6));
        Artisan::call('whitelabel:intake-deploy-check');
        Carbon::setTestNow();

        $this->assertSame(WhiteLabelProjectIntake::STATUS_COMPLETED, $intake->fresh()->status);
        $this->assertNotNull($intake->fresh()->deploy_completed_at);
        Notification::assertSentToTimes($owner, WhiteLabelDeploymentReadyNotification::class, 1);
    }

    public function test_it_leaves_a_non_elapsed_intake_untouched(): void
    {
        Notification::fake();
        $intake = $this->inProgressIntake(10);

        Carbon::setTestNow(now()->addDays(2));
        Artisan::call('whitelabel:intake-deploy-check');
        Carbon::setTestNow();

        $this->assertSame(WhiteLabelProjectIntake::STATUS_IN_PROGRESS, $intake->fresh()->status);
        $this->assertNull($intake->fresh()->deploy_completed_at);
        Notification::assertNothingSent();
    }

    public function test_it_isolates_a_per_row_failure_and_still_completes_the_rest(): void
    {
        $this->configureMail();
        Notification::fake();
        $broken = $this->inProgressIntake(1);
        $healthyOwner = User::factory()->create();
        $healthy = $this->inProgressIntake(1, $healthyOwner);

        // Force a real exception for one specific row (simulating e.g. a
        // provider/notification failure) without touching the other, by
        // wrapping the real service in a partial mock.
        $real = $this->intakes();
        $mock = Mockery::mock($real)->makePartial();
        $mock->shouldReceive('completeIfElapsed')
            ->andReturnUsing(function (WhiteLabelProjectIntake $intake) use ($broken, $real) {
                if ($intake->id === $broken->id) {
                    throw new \RuntimeException('simulated failure');
                }

                return $real->completeIfElapsed($intake);
            });
        $this->app->instance(WhiteLabelProjectIntakeService::class, $mock);

        Carbon::setTestNow(now()->addDays(2));
        Artisan::call('whitelabel:intake-deploy-check');
        Carbon::setTestNow();

        $this->assertSame(WhiteLabelProjectIntake::STATUS_IN_PROGRESS, $broken->fresh()->status);
        $this->assertSame(WhiteLabelProjectIntake::STATUS_COMPLETED, $healthy->fresh()->status);
        Notification::assertSentToTimes($healthyOwner, WhiteLabelDeploymentReadyNotification::class, 1);
    }
}
