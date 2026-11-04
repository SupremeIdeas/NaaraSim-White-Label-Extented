<?php

namespace Tests\Feature;

use App\Support\Activation\LicenseState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhiteLabelDoctorLicenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_activated_install_without_the_platform_public_key_is_a_failure_with_the_fix_named(): void
    {
        config(['updater.licence_public_key' => null]);
        LicenseState::storeActivation('https://p.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        $this->artisan('whitelabel:doctor')->expectsOutputToContain('Platform public key')->assertFailed();
    }

    public function test_with_the_key_present_the_licence_section_reports_standing_and_checkin(): void
    {
        $kp = sodium_crypto_sign_keypair();
        config(['updater.licence_public_key' => base64_encode(sodium_crypto_sign_publickey($kp))]);
        LicenseState::storeActivation('https://p.example', '1|abc', 'ABCD', ['domain' => 'x.test']);
        $this->artisan('whitelabel:doctor')->expectsOutputToContain('answers are verified')->expectsOutputToContain('Last successful check-in');
    }
}
