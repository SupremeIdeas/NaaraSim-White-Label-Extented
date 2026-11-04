<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Activation\Enforcement;
use App\Support\Activation\LicenseState;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforcementStagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_unactivated_install_is_in_a_14_day_grace_with_a_countdown(): void
    {
        $this->travelTo(now());
        User::factory()->create(['created_at' => now()->subDays(2)]);
        $s = Enforcement::stage();
        $this->assertSame('grace', $s['stage']);
        $this->assertSame(12, $s['days_left']);
        $this->assertTrue(Enforcement::updatesAllowed());
    }

    public function test_after_14_days_unactivated_the_stage_is_firm_and_updates_are_withheld(): void
    {
        User::factory()->create(['created_at' => now()->subDays(15)]);
        $s = Enforcement::stage();
        $this->assertSame('expired', $s['stage']);
        $this->assertFalse(Enforcement::updatesAllowed());
    }

    public function test_waiting_does_not_reset_the_clock(): void
    {
        $u = User::factory()->create(['created_at' => now()->subDays(20)]);
        Enforcement::stage();
        $u->forceFill(['created_at' => now()])->save();
        $this->assertSame('expired', Enforcement::stage()['stage'], 'the earliest moment ever seen wins');
    }

    public function test_an_activated_site_in_good_standing_sees_no_banner_and_may_update(): void
    {
        LicenseState::storeActivation('https://p.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        $this->assertSame('ok', Enforcement::stage()['stage']);
        $this->assertTrue(Enforcement::updatesAllowed());
    }

    public function test_suspended_and_revoked_are_firm_but_unreachable_is_only_a_note(): void
    {
        LicenseState::storeActivation('https://p.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        foreach (['suspended' => false, 'revoked' => false, 'unreachable' => true] as $status => $mayUpdate) {
            LicenseState::put('status', $status);
            $this->assertSame($status, Enforcement::stage()['stage']);
            $this->assertSame($mayUpdate, Enforcement::updatesAllowed(), $status);
        }
    }

    public function test_the_banner_shows_to_admins_with_an_activate_button_and_not_on_the_activation_page(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['created_at' => now()->subDays(20)]);
        $admin->assignRole('super_admin');
        $p = '/'.config('admin.path');
        $this->actingAs($admin)->get($p)->assertOk()->assertSee('data-licence-banner="expired"', false)->assertSee('Activate now');
        $this->actingAs($admin)->get($p.'/license')->assertOk()->assertDontSee('data-licence-banner', false);
    }

    public function test_an_untrusted_answer_is_firm_and_withholds_updates(): void
    {
        LicenseState::storeActivation('https://p.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        LicenseState::put('status', 'unverified');
        $this->assertSame('unverified', Enforcement::stage()['stage']);
        $this->assertFalse(Enforcement::updatesAllowed());
    }

    public function test_a_month_without_a_signed_confirmation_is_a_lapse_and_a_week_is_a_warning(): void
    {
        $kp = sodium_crypto_sign_keypair();
        config(['updater.licence_public_key' => base64_encode(sodium_crypto_sign_publickey($kp))]);
        LicenseState::storeActivation('https://p.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        LicenseState::put('verified_at', now()->subDays(9)->toIso8601String());
        $this->assertSame('stale', Enforcement::stage()['stage']);
        $this->assertTrue(Enforcement::updatesAllowed(), 'a warning does not withhold anything');
        LicenseState::put('verified_at', now()->subDays(40)->toIso8601String());
        $this->assertSame('lapsed', Enforcement::stage()['stage']);
        $this->assertFalse(Enforcement::updatesAllowed());
    }
}
