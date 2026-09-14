<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelProjectIntake;
use App\Notifications\WhiteLabelDeploymentReadyNotification;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Updater\WhiteLabelProjectIntakeException;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Prompt 21-EXT2 §2/§3 — the post-purchase project-commencement brief: a
 * merchant can only file it once their license is live, hosting credentials
 * are encrypted at rest and only collected for a self-hosted choice, a
 * deploy timeline can't be set before an admin marks the brief Seen, and
 * progress is derived from exactly one calculation both the merchant
 * dashboard and the admin panel read.
 */
class WhiteLabelProjectIntakeTest extends TestCase
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

    private function licensedInstance(): WhiteLabelInstance
    {
        $instance = $this->licenses()->register(['brand_name' => 'Acme', 'contact_email' => 'a@acme.test']);

        return $this->licenses()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
    }

    public function test_submitting_requires_a_live_license(): void
    {
        $instance = $this->licenses()->register(['brand_name' => 'Pending', 'contact_email' => 'p@p.test']);

        $this->expectException(WhiteLabelProjectIntakeException::class);
        $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+15551234567',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);
    }

    public function test_submitting_for_managed_hosting_stores_no_credentials(): void
    {
        $instance = $this->licensedInstance();

        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+15551234567',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
            'hosting_host' => 'should-be-ignored.test', 'hosting_username' => 'ignored',
        ]);

        $this->assertSame('My Brand', $intake->desired_brand_name);
        $this->assertSame(WhiteLabelProjectIntake::STATUS_PENDING, $intake->status);
        $this->assertNull($intake->hosting_host);
        $this->assertNull($intake->hosting_username);
        $this->assertNull($intake->hosting_disclaimer_acknowledged_at);
    }

    public function test_submitting_for_own_vps_stores_encrypted_credentials(): void
    {
        $instance = $this->licensedInstance();

        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+15551234567',
            'hosting_choice' => WhiteLabelInstance::HOSTING_OWN_VPS,
            'hosting_disclaimer_acknowledged' => true,
            'hosting_host' => 'my-vps.cloudwaysapps.com',
            'hosting_username' => 'root',
            'hosting_password' => 'S3cret!',
            'hosting_notes' => 'SSH key attached separately',
        ]);

        $this->assertSame('my-vps.cloudwaysapps.com', $intake->hosting_host);
        $this->assertSame('root', $intake->hosting_username);
        $this->assertSame('S3cret!', $intake->hosting_password);
        $this->assertNotNull($intake->hosting_disclaimer_acknowledged_at);

        // Encrypted at rest: the raw DB column never carries the plaintext.
        $raw = \DB::table('white_label_project_intakes')->where('id', $intake->id)->value('hosting_password');
        $this->assertStringNotContainsString('S3cret!', $raw);

        // Hidden from array/JSON serialization.
        $this->assertArrayNotHasKey('hosting_password', $intake->toArray());
    }

    public function test_resubmitting_before_review_overwrites_the_draft(): void
    {
        $instance = $this->licensedInstance();
        $this->intakes()->submit($instance, [
            'desired_brand_name' => 'First Name', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);

        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'Second Name', 'whatsapp_number' => '+2',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);

        $this->assertSame(1, WhiteLabelProjectIntake::count());
        $this->assertSame('Second Name', $intake->desired_brand_name);
    }

    public function test_mark_seen_records_the_reviewer(): void
    {
        $instance = $this->licensedInstance();
        $admin = User::factory()->create();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);

        $this->intakes()->markSeen($intake, $admin->id);

        $this->assertSame(WhiteLabelProjectIntake::STATUS_SEEN, $intake->fresh()->status);
        $this->assertSame($admin->id, $intake->fresh()->reviewed_by);
        $this->assertNotNull($intake->fresh()->reviewed_at);
    }

    public function test_deploy_timeline_cannot_be_set_before_the_intake_is_seen(): void
    {
        $instance = $this->licensedInstance();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);

        $this->expectException(WhiteLabelProjectIntakeException::class);
        $this->intakes()->setDeployTimeline($intake, 7);
    }

    public function test_deploy_timeline_starts_the_autopilot_progress(): void
    {
        $instance = $this->licensedInstance();
        $admin = User::factory()->create();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);
        $this->intakes()->markSeen($intake, $admin->id);

        $this->intakes()->setDeployTimeline($intake, 10);
        $intake->refresh();

        $this->assertSame(WhiteLabelProjectIntake::STATUS_IN_PROGRESS, $intake->status);
        $this->assertSame(10, $intake->deploy_days);
        $this->assertNotNull($intake->deploy_started_at);
        $this->assertSame(0, $this->intakes()->progressPercent($intake));
    }

    public function test_progress_percent_reflects_elapsed_time_and_caps_at_100(): void
    {
        $instance = $this->licensedInstance();
        $admin = User::factory()->create();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);
        $this->intakes()->markSeen($intake, $admin->id);
        $this->intakes()->setDeployTimeline($intake, 10);
        $intake->refresh();

        Carbon::setTestNow(now()->addDays(5));
        $this->assertSame(50, $this->intakes()->progressPercent($intake));
        $day = $this->intakes()->dayOf($intake);
        $this->assertSame(6, $day['day']);
        $this->assertSame(10, $day['of']);

        Carbon::setTestNow(now()->addDays(20));
        $this->assertSame(100, $this->intakes()->progressPercent($intake));
        Carbon::setTestNow();
    }

    public function test_progress_percent_is_null_before_a_timeline_is_set(): void
    {
        $instance = $this->licensedInstance();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);

        $this->assertNull($this->intakes()->progressPercent($intake));
        $this->assertNull($this->intakes()->dayOf($intake));
    }

    public function test_complete_if_elapsed_is_a_no_op_when_not_in_progress(): void
    {
        Notification::fake();
        $instance = $this->licensedInstance();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);

        $this->assertFalse($this->intakes()->completeIfElapsed($intake));
        $this->assertSame(WhiteLabelProjectIntake::STATUS_PENDING, $intake->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_complete_if_elapsed_is_a_no_op_before_100_percent(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $instance = $this->licenses()->register(['brand_name' => 'Acme', 'contact_email' => 'a@acme.test', 'owner_user_id' => $owner->id]);
        $instance = $this->licenses()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
        $admin = User::factory()->create();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);
        $this->intakes()->markSeen($intake, $admin->id);
        $this->intakes()->setDeployTimeline($intake, 10);
        $intake->refresh();

        Carbon::setTestNow(now()->addDays(5));
        $this->assertFalse($this->intakes()->completeIfElapsed($intake));
        $this->assertSame(WhiteLabelProjectIntake::STATUS_IN_PROGRESS, $intake->fresh()->status);
        Notification::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_complete_if_elapsed_flips_to_completed_and_notifies_the_owner_once(): void
    {
        $this->configureMail();
        Notification::fake();
        $owner = User::factory()->create();
        $instance = $this->licenses()->register(['brand_name' => 'Acme', 'contact_email' => 'a@acme.test', 'owner_user_id' => $owner->id]);
        $instance = $this->licenses()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
        $admin = User::factory()->create();
        $intake = $this->intakes()->submit($instance, [
            'desired_brand_name' => 'My Brand', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);
        $this->intakes()->markSeen($intake, $admin->id);
        $this->intakes()->setDeployTimeline($intake, 10);
        $intake->refresh();

        Carbon::setTestNow(now()->addDays(11));
        $this->assertTrue($this->intakes()->completeIfElapsed($intake));
        $intake->refresh();

        $this->assertSame(WhiteLabelProjectIntake::STATUS_COMPLETED, $intake->status);
        $this->assertNotNull($intake->deploy_completed_at);
        Notification::assertSentToTimes($owner, WhiteLabelDeploymentReadyNotification::class, 1);

        // Idempotent: calling it again must not re-notify or change anything.
        $this->assertFalse($this->intakes()->completeIfElapsed($intake));
        Notification::assertSentToTimes($owner, WhiteLabelDeploymentReadyNotification::class, 1);
        Carbon::setTestNow();
    }
}
