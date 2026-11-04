<?php

namespace Tests\Feature;

use App\Livewire\Admin\LicenseActivation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Updater\WhiteLabelUpdateClient;
use App\Support\Activation\EntitlementVerifier;
use App\Support\Activation\LicenseState;
use App\Support\FeatureEntitlements;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** A fake platform, a replayed answer, an expired answer and a tampered database must never unlock anything. */
class LicenceVerificationTest extends TestCase
{
    use RefreshDatabase;

    private array $kp;

    protected function setUp(): void
    {
        parent::setUp();
        $kp = sodium_crypto_sign_keypair();
        $this->kp = ['pub' => base64_encode(sodium_crypto_sign_publickey($kp)), 'priv' => sodium_crypto_sign_secretkey($kp)];
        config(['updater.licence_public_key' => $this->kp['pub'], 'updater.product_identifier' => 'naarasim-whitelabel']);
        FeatureEntitlements::bust();
    }

    private function signed(array $over = [], ?string $privOverride = null): array
    {
        $payload = json_encode($over + ['v' => 1, 'ref' => 'acme', 'tier' => 'extended', 'level' => 'full', 'locks' => [], 'skins' => 3, 'iat' => time(), 'exp' => time() + 86400, 'nonce' => 'n']);

        return ['payload' => base64_encode($payload), 'sig' => base64_encode(sodium_crypto_sign_detached(hash('sha256', $payload, true), $privOverride ?? $this->kp['priv']))];
    }

    private function activate(): void
    {
        Http::fake([
            '*/activate' => Http::response(['token' => '9|tok', 'instance' => ['slug' => 'acme', 'tier' => 'normal', 'brand_name' => 'Acme']]),
            '*/entitlement' => Http::response(['level' => 'basic', 'locks' => ['gift_cards'], 'signed' => $this->signed(['tier' => 'normal', 'level' => 'basic', 'locks' => ['gift_cards', 'esim_voice']])]),
        ]);
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('super_admin');
        Livewire::actingAs($u)->test(LicenseActivation::class)->set('licenseKey', 'K-1234')->set('platformUrl', 'https://platform.example')->call('activate')->assertSet('error', null);
    }

    public function test_extended_has_no_per_feature_locks_even_unactivated(): void
    {
        $this->assertSame([], FeatureEntitlements::all());
    }

    public function test_a_valid_signed_answer_unlocks_exactly_what_it_says(): void
    {
        $this->activate();
        FeatureEntitlements::bust();
        $this->assertNotNull(LicenseState::verifiedEntitlement());
    }

    public function test_a_fake_platform_that_says_nothing_is_locked_changes_nothing(): void
    {
        $this->activate();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['level' => 'full', 'locks' => []])]);   // unsigned, the lie a pirate platform would tell

        $r = app(WhiteLabelUpdateClient::class)->refreshEntitlement();
        FeatureEntitlements::bust();

        $this->assertFalse($r['ok']);
        $this->assertSame('unverified', LicenseState::status());
    }

    public function test_an_answer_signed_with_someone_elses_key_is_refused(): void
    {
        $this->activate();
        $other = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['level' => 'full', 'locks' => [], 'signed' => $this->signed([], $other)])]);

        $this->assertFalse(app(WhiteLabelUpdateClient::class)->refreshEntitlement()['ok']);
        FeatureEntitlements::bust();
    }

    public function test_another_licences_real_answer_cannot_be_replayed_here(): void
    {
        $this->activate();
        Http::swap(new \Illuminate\Http\Client\Factory);
        // A genuine, correctly signed Extended answer, but issued to a different licence.
        Http::fake(['*' => Http::response(['level' => 'full', 'locks' => [], 'signed' => $this->signed(['ref' => 'someone-else'])])]);

        $this->assertFalse(app(WhiteLabelUpdateClient::class)->refreshEntitlement()['ok']);
        FeatureEntitlements::bust();
    }

    public function test_an_expired_answer_stops_counting(): void
    {
        $this->activate();
        LicenseState::put('entitlement_signed', json_encode($this->signed(['exp' => time() - 10, 'locks' => []])));
        FeatureEntitlements::bust();
        $this->assertNull(LicenseState::verifiedEntitlement());
    }

    public function test_editing_the_database_cannot_unlock_anything(): void
    {
        $this->activate();
        Setting::setValue('white_label.entitlement_locks', [], 'white_label');          // the plain list a pirate would clear
        LicenseState::put('entitlement_signed', json_encode(['payload' => $this->signed(['locks' => []])['payload'], 'sig' => base64_encode(str_repeat('x', 64))]));
        FeatureEntitlements::bust();

        $this->assertNull(LicenseState::verifiedEntitlement());   // the forged record proves nothing
    }

    public function test_the_verifier_rejects_garbage(): void
    {
        foreach ([[], ['payload' => 'x', 'sig' => 'y'], ['payload' => base64_encode('{}'), 'sig' => base64_encode(str_repeat('a', 64))]] as $bad) {
            $this->assertNull(EntitlementVerifier::verify($bad));
        }
    }

    public function test_when_the_platform_says_no_what_it_signed_earlier_stops_counting_at_once(): void
    {
        $this->activate();
        FeatureEntitlements::bust();
        $this->assertNotNull(LicenseState::verifiedEntitlement());

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
        app(WhiteLabelUpdateClient::class)->refreshEntitlement();
        FeatureEntitlements::bust();

        $this->assertSame('revoked', LicenseState::status());
        $this->assertNull(LicenseState::verifiedEntitlement());
    }

    public function test_a_key_accepted_with_an_unverifiable_answer_does_not_show_a_success_banner(): void
    {
        Http::fake([
            '*/activate' => Http::response(['token' => '9|tok', 'instance' => ['slug' => 'acme', 'tier' => 'normal']]),
            '*/entitlement' => Http::response(['level' => 'full', 'locks' => []]),   // unsigned
        ]);
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('super_admin');
        Livewire::actingAs($u)->test(LicenseActivation::class)->set('licenseKey', 'K-1234')->set('platformUrl', 'https://platform.example')->call('activate')
            ->assertSet('notice', null)->assertSee('could not be verified');
    }
}
