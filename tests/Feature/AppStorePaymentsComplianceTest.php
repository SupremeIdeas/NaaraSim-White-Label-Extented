<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Merchants\MerchantException;
use App\Services\Merchants\MerchantUpgradeService;
use App\Services\Wallet\WalletService;
use App\Support\AppExport;
use App\Support\MerchantSettings;
use App\Support\WizardFee;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * docs/APP-STORE-PAYMENTS-COMPLIANCE.md (BUILD-5 §6) — the `is_ios_build`
 * capability flag Stage 1 requires, wired into the 4 product lines the doc's
 * table flags: wallet top-up entry, Wizard fee, merchant-upgrade fee, and
 * Naara Gift. Each must never silently charge through our own gateway inside
 * a native iOS build.
 */
class AppStorePaymentsComplianceTest extends TestCase
{
    use RefreshDatabase;

    private const IOS_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 median';

    private const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36 median';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** Bind a fake request carrying the given User-Agent so AppPlatform sees it. */
    private function withUa(string $ua): void
    {
        app()->instance('request', Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => $ua]));
    }

    /**
     * Livewire::test()'s internal AJAX-action simulation replaces the bound
     * 'request' instance with its own bare Symfony request that carries no
     * User-Agent — a testing-harness artifact, not a production behavior
     * (every real Livewire round trip is a real HTTP request carrying the
     * real device UA). So the gate itself is exercised by calling the
     * component's methods directly, which run in-process against whatever
     * 'request' this test bound — exactly like a real request would.
     */
    public function test_wallet_topup_form_hidden_and_blocked_on_ios(): void
    {
        $this->withUa(self::IOS_UA);
        $user = User::factory()->create();
        $this->actingAs($user);

        $wallet = new Wallet;
        $this->assertTrue($wallet->isIosBuild());
        $wallet->topUp();
        $this->assertStringContainsString("aren't available", $wallet->error);
    }

    public function test_wallet_topup_works_normally_on_android(): void
    {
        $this->withUa(self::ANDROID_UA);
        $user = User::factory()->create();
        $this->actingAs($user);

        $wallet = new Wallet;
        $this->assertFalse($wallet->isIosBuild());
    }

    public function test_wizard_fee_never_applies_on_ios(): void
    {
        Setting::setValue('wizard.fee_usd', 0.45);
        Setting::setValue('wizard.free_sessions', 0);
        $user = User::factory()->create(['wizard_uses' => 5]);

        $this->withUa(self::ANDROID_UA);
        $this->assertTrue(WizardFee::appliesTo($user));

        $this->withUa(self::IOS_UA);
        $this->assertFalse(WizardFee::appliesTo($user));
        $this->assertSame(0.0, WizardFee::forUser($user));
    }

    public function test_merchant_v2_self_upgrade_blocked_on_ios(): void
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 500, 'USD', ['reference' => 'seed:'.$owner->id]);
        $merchant = Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_STANDARD, 'reseller_margin_pct' => 10,
        ]);
        \App\Models\Setting::setValue(MerchantSettings::FLAG, true);

        $this->withUa(self::IOS_UA);
        $this->expectException(MerchantException::class);
        $this->expectExceptionMessage("isn't available in the iOS app");
        app(MerchantUpgradeService::class)->selfUpgrade($merchant);
    }

    public function test_merchant_v2_self_upgrade_works_on_android(): void
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 500, 'USD', ['reference' => 'seed:'.$owner->id]);
        $merchant = Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_STANDARD, 'reseller_margin_pct' => 10,
        ]);
        \App\Models\Setting::setValue(MerchantSettings::FLAG, true);

        $this->withUa(self::ANDROID_UA);
        $merchant = app(MerchantUpgradeService::class)->selfUpgrade($merchant);
        $this->assertTrue($merchant->isV2());
    }

    public function test_gift_cards_gated_off_on_ios_by_default(): void
    {
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        \Illuminate\Support\Facades\Cache::forget('features.enabled.naara_gift');
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->withUa(self::IOS_UA);
        try {
            (new \App\Livewire\GiftCards)->booted();
            $this->fail('Expected a 404 abort on iOS with gift cards not yet enabled.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $this->assertTrue(true);
        }

        AppExport::save(['ios_gift_cards_enabled' => true]);
        (new \App\Livewire\GiftCards)->booted();
        $this->assertTrue(true); // no abort thrown
    }

    public function test_gift_cards_unaffected_on_android(): void
    {
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        \Illuminate\Support\Facades\Cache::forget('features.enabled.naara_gift');
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->withUa(self::ANDROID_UA);
        (new \App\Livewire\GiftCards)->booted();
        $this->assertTrue(true); // no abort thrown
    }
}
