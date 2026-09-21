<?php

namespace Tests\Feature;

use App\Livewire\Admin\KycReview;
use App\Livewire\IdentityVerification;
use App\Models\KycVerification;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\KycResultNotification;
use App\Services\Kyc\KycService;
use App\Support\KycSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 0.3 — KYC. Manual review is the default; providers can decide
 * synchronously or via a signed webhook. hasLevel() is the gate the withdraw and
 * merchant flows consult.
 */
class KycTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function kyc(): KycService
    {
        return app(KycService::class);
    }

    public function test_manual_submit_is_pending_then_admin_approval_grants_the_level(): void
    {
        $user = User::factory()->create();

        $v = $this->kyc()->submit($user, KycVerification::L2, ['id_type' => 'BVN', 'id_number' => '123', 'country' => 'NG']);
        $this->assertSame(KycVerification::PENDING, $v->status);
        $this->assertFalse($this->kyc()->hasLevel($user, KycVerification::L2));

        $this->kyc()->approve($v, $this->admin());
        $this->assertTrue($this->kyc()->hasLevel($user->fresh(), KycVerification::L2));
        $this->assertSame(2, $this->kyc()->currentLevel($user->fresh()));
    }

    public function test_the_raw_id_number_is_never_stored(): void
    {
        $user = User::factory()->create();
        $this->kyc()->submit($user, KycVerification::L2, ['id_type' => 'BVN', 'id_number' => 'SECRET-9999', 'country' => 'NG']);

        $this->assertDatabaseMissing('kyc_verifications', ['reason' => 'SECRET-9999']);
        $row = KycVerification::first();
        $this->assertStringNotContainsString('SECRET-9999', json_encode($row->checks));
    }

    public function test_resubmitting_while_pending_reuses_the_attempt(): void
    {
        $user = User::factory()->create();
        $a = $this->kyc()->submit($user, KycVerification::L2, ['id_number' => '1']);
        $b = $this->kyc()->submit($user, KycVerification::L2, ['id_number' => '2']);

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('kyc_verifications', 1);
    }

    public function test_dojah_decides_synchronously_when_configured(): void
    {
        config(['services.dojah.app_id' => 'app', 'services.dojah.api_key' => 'key']);
        Setting::setValue(KycSettings::PROVIDER, 'dojah', 'kyc');
        Http::fake(['*/api/v1/kyc/nin*' => Http::response(['entity' => ['first_name' => 'Jane']], 200)]);

        $user = User::factory()->create();
        $v = $this->kyc()->submit($user, KycVerification::L2, ['id_number' => '12345678901']);

        $this->assertSame(KycVerification::APPROVED, $v->status);
        $this->assertTrue($this->kyc()->hasLevel($user, KycVerification::L2));
    }

    public function test_a_signed_smileid_callback_settles_the_verification(): void
    {
        config(['services.smileid.api_key' => 'sk_smile', 'services.smileid.partner_id' => 'p1']);
        $user = User::factory()->create();
        $v = KycVerification::create([
            'user_id' => $user->id, 'level' => 2, 'provider' => 'smileid',
            'status' => KycVerification::PENDING, 'reference' => 'kyc:smile-1',
        ]);

        $body = json_encode(['partner_params' => ['job_id' => 'kyc:smile-1'], 'ResultCode' => '1012']);
        $signature = hash_hmac('sha256', $body, 'sk_smile');

        $this->call('POST', '/webhooks/kyc/smileid', [], [], [],
            ['HTTP_X-SMILEID-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();

        $this->assertSame(KycVerification::APPROVED, $v->fresh()->status);
    }

    public function test_a_bad_kyc_signature_is_rejected(): void
    {
        config(['services.smileid.api_key' => 'sk_smile']);
        $body = json_encode(['partner_params' => ['job_id' => 'x'], 'ResultCode' => '1012']);

        $this->call('POST', '/webhooks/kyc/smileid', [], [], [],
            ['HTTP_X-SMILEID-SIGNATURE' => 'wrong', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);
    }

    public function test_customer_can_submit_identity_from_the_page(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(IdentityVerification::class)
            ->set('country', 'NG')->set('idType', 'BVN')->set('idNumber', '22222222222')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('kyc_verifications', ['user_id' => $user->id, 'level' => 2, 'status' => 'pending']);
    }

    public function test_admin_kyc_page_is_admin_only_and_approves(): void
    {
        Livewire::actingAs(User::factory()->create())->test(KycReview::class)->assertForbidden();

        $user = User::factory()->create();
        $v = $this->kyc()->submit($user, KycVerification::L2, ['id_number' => '1']);

        Livewire::actingAs($this->admin())->test(KycReview::class)
            ->call('approve', $v->id);

        $this->assertTrue($this->kyc()->hasLevel($user->fresh(), 2));
    }

    public function test_the_user_is_notified_on_approval_and_rejection(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $v = $this->kyc()->submit($user, KycVerification::L2, ['id_number' => '1']);

        $this->kyc()->approve($v, $this->admin());
        Notification::assertSentTo($user, KycResultNotification::class, fn ($n) => $n->status === KycVerification::APPROVED);

        $user2 = User::factory()->create();
        $v2 = $this->kyc()->submit($user2, KycVerification::L2, ['id_number' => '2']);
        $this->kyc()->reject($v2, $this->admin(), 'Document unreadable');
        Notification::assertSentTo($user2, KycResultNotification::class, fn ($n) => $n->status === KycVerification::REJECTED && $n->reason === 'Document unreadable');
    }

    public function test_a_pending_verification_never_notifies(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->kyc()->submit($user, KycVerification::L2, ['id_number' => '1']);

        Notification::assertNothingSentTo($user);
    }

    public function test_admin_can_switch_the_active_provider(): void
    {
        Livewire::actingAs($this->admin())->test(KycReview::class)
            ->set('provider', 'dojah')
            ->call('saveProvider')
            ->assertHasNoErrors();

        $this->assertSame('dojah', KycSettings::provider());
    }

    // -- Tier 5 #11 Phase C: automated business KYB (Nigeria/CAC via Dojah) --

    public function test_a_nigerian_cac_submission_is_automatically_verified_via_dojah(): void
    {
        config(['services.dojah.app_id' => 'app', 'services.dojah.api_key' => 'key']);
        Setting::setValue(KycSettings::PROVIDER, 'dojah', 'kyc');
        Http::fake(['*/api/v1/kyc/cac*' => Http::response(['entity' => ['company_name' => 'Naara Traders Ltd']], 200)]);

        $user = User::factory()->create();
        $v = $this->kyc()->submit($user, KycVerification::L3, [
            'country' => 'NG', 'id_type' => 'CAC', 'id_number' => 'RC123456',
        ]);

        $this->assertSame('dojah', $v->provider);
        $this->assertSame(KycVerification::APPROVED, $v->status);
        $this->assertSame('Naara Traders Ltd', $v->checks['company_name']);
        $this->assertTrue($this->kyc()->hasLevel($user, KycVerification::L3));
    }

    public function test_a_nigerian_cac_submission_not_found_is_rejected(): void
    {
        config(['services.dojah.app_id' => 'app', 'services.dojah.api_key' => 'key']);
        Setting::setValue(KycSettings::PROVIDER, 'dojah', 'kyc');
        Http::fake(['*/api/v1/kyc/cac*' => Http::response(['entity' => null], 200)]);

        $user = User::factory()->create();
        $v = $this->kyc()->submit($user, KycVerification::L3, [
            'country' => 'NG', 'id_type' => 'CAC', 'id_number' => 'RC000000',
        ]);

        $this->assertSame(KycVerification::REJECTED, $v->status);
    }

    /**
     * A Nigerian applicant registering under TIN (not CAC) has no automated
     * Dojah product behind it — it must sit pending for a human, not get
     * silently rejected by a lookup that was never going to answer for it.
     */
    public function test_a_nigerian_tin_submission_stays_pending_for_manual_review(): void
    {
        config(['services.dojah.app_id' => 'app', 'services.dojah.api_key' => 'key']);
        Setting::setValue(KycSettings::PROVIDER, 'dojah', 'kyc');

        $user = User::factory()->create();
        $v = $this->kyc()->submit($user, KycVerification::L3, [
            'country' => 'NG', 'id_type' => 'TIN', 'id_number' => '12345678',
        ]);

        $this->assertSame('dojah', $v->provider);
        $this->assertSame(KycVerification::PENDING, $v->status);
    }

    /**
     * No confirmed automated business-registry lookup exists outside Nigeria
     * — a non-NG L3 submission goes straight to manual review rather than
     * being routed through Dojah/Smile ID's personal-ID endpoints, which
     * would silently misfire on a business registration number.
     */
    public function test_a_non_nigerian_business_submission_goes_straight_to_manual_review(): void
    {
        config(['services.dojah.app_id' => 'app', 'services.dojah.api_key' => 'key']);
        Setting::setValue(KycSettings::PROVIDER, 'dojah', 'kyc');

        $user = User::factory()->create();
        $v = $this->kyc()->submit($user, KycVerification::L3, [
            'country' => 'GB', 'id_type' => 'CRN', 'id_number' => '01234567',
        ]);

        $this->assertSame('manual', $v->provider);
        $this->assertSame(KycVerification::PENDING, $v->status);
    }

    public function test_the_become_merchant_kyb_form_submits_a_real_l3_verification(): void
    {
        config(['services.dojah.app_id' => 'app', 'services.dojah.api_key' => 'key']);
        Setting::setValue(KycSettings::PROVIDER, 'dojah', 'kyc');
        Http::fake(['*/api/v1/kyc/cac*' => Http::response(['entity' => ['company_name' => 'Ada Foods']], 200)]);

        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(\App\Livewire\BecomeMerchant::class)
            ->set('country', 'NG')->set('regType', 'CAC')->set('regNumber', 'RC998877')
            ->call('submitKyb')
            ->assertHasNoErrors();

        $this->assertTrue($this->kyc()->hasLevel($user, KycVerification::L3));
    }
}
