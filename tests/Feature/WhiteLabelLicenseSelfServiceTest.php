<?php

namespace Tests\Feature;

use App\Exceptions\LicenseActivationException;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePayment;
use App\Models\WhiteLabelLicensePlan;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Wallet\WalletService;
use App\Support\ThemeAddonCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 21-EXT §3 — the merchant self-service payment flow: a priced pending
 * request pays once to get its FIRST license (payAndActivate), and a live
 * Normal-tier instance can later pay off the remaining balance to become
 * Extended (payBalanceAndUpgrade) WITHOUT re-issuing its key or killing its
 * already-deployed fork's Sanctum token — the whole reason upgradeTier()
 * exists as a non-destructive sibling to issueLicense().
 */
class WhiteLabelLicenseSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WhiteLabelLicenseService
    {
        return app(WhiteLabelLicenseService::class);
    }

    private function fundedUser(float $usd = 10000): User
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $usd, 'USD');

        return $user;
    }

    // --- register(): self-service fields ---

    public function test_register_carries_the_plan_merchant_and_hosting_fields(): void
    {
        $plan = WhiteLabelLicensePlan::create([
            'key' => 'basic', 'name' => 'Basic', 'tagline' => 't', 'description' => 'd',
            'price_usd' => 1500, 'tier' => WhiteLabelInstance::TIER_NORMAL, 'is_active' => true,
        ]);
        $owner = User::factory()->create();
        $merchant = \App\Models\Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => 'active', 'tier' => \App\Models\Merchant::TIER_V2,
        ]);

        $instance = $this->service()->register([
            'brand_name' => 'Self Service Co', 'contact_email' => 's@co.test',
            'merchant_id' => $merchant->id, 'license_plan_id' => $plan->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
            'hosting_preference' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
            'hosting_disclaimer_acknowledged' => true,
        ]);

        $this->assertSame($merchant->id, $instance->merchant_id);
        $this->assertSame($plan->id, $instance->license_plan_id);
        $this->assertSame(WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE, $instance->acquisition_method);
        $this->assertSame(WhiteLabelInstance::TIER_NORMAL, $instance->requested_tier);
        $this->assertSame(WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER, $instance->hosting_preference);
        $this->assertNotNull($instance->hosting_disclaimer_acknowledged_at);
    }

    // --- register(): theme add-on (owner request, 2026-09-15) ---

    public function test_register_snapshots_the_theme_addon_price_at_request_time(): void
    {
        $instance = $this->service()->register([
            'brand_name' => 'Themed Co', 'contact_email' => 't@co.test',
            'theme_addon' => ThemeAddonCatalog::ELEGANT,
        ]);

        $this->assertSame(ThemeAddonCatalog::ELEGANT, $instance->theme_addon);
        $this->assertSame('2900.00', (string) $instance->theme_addon_price_usd);
        $this->assertTrue($instance->hasThemeAddon());
    }

    public function test_register_with_no_addon_or_none_stores_null_and_zero_extra_cost(): void
    {
        $noAddon = $this->service()->register(['brand_name' => 'Plain Co', 'contact_email' => 'p@co.test']);
        $this->assertNull($noAddon->theme_addon);
        $this->assertNull($noAddon->theme_addon_price_usd);
        $this->assertFalse($noAddon->hasThemeAddon());

        $explicitNone = $this->service()->register([
            'brand_name' => 'Explicit None Co', 'contact_email' => 'e@co.test',
            'theme_addon' => ThemeAddonCatalog::NONE,
        ]);
        $this->assertNull($explicitNone->theme_addon);
        $this->assertFalse($explicitNone->hasThemeAddon());
    }

    public function test_register_degrades_an_unknown_theme_addon_key_to_none(): void
    {
        $instance = $this->service()->register([
            'brand_name' => 'Bad Key Co', 'contact_email' => 'bk@co.test',
            'theme_addon' => 'not-a-real-tier',
        ]);

        $this->assertNull($instance->theme_addon);
        $this->assertFalse($instance->hasThemeAddon());
    }

    public function test_a_later_admin_price_retune_never_changes_an_already_requested_instances_addon_price(): void
    {
        $instance = $this->service()->register([
            'brand_name' => 'Snapshot Co', 'contact_email' => 's2@co.test',
            'theme_addon' => ThemeAddonCatalog::BASIC,
        ]);
        $this->assertSame('1200.00', (string) $instance->theme_addon_price_usd);

        \App\Models\Setting::setValue('whitelabel.theme_addon.price.basic', 9999.00);

        $this->assertSame('1200.00', (string) $instance->fresh()->theme_addon_price_usd, 'a retuned catalog price must never retroactively change an already-snapshotted instance');
        $this->assertSame(9999.0, ThemeAddonCatalog::priceFor(ThemeAddonCatalog::BASIC), 'but new requests DO see the retuned price');
    }

    // --- payAndActivate(): theme add-on billed in the SAME atomic charge ---

    public function test_pay_and_activate_bills_the_theme_addon_in_one_combined_charge_with_its_own_ledger_row(): void
    {
        // 1500 license + 4000 Premium theme add-on = 5500 total.
        $payer = $this->fundedUser(5500);
        $instance = $this->service()->register([
            'brand_name' => 'Addon Co', 'contact_email' => 'ad@co.test',
            'theme_addon' => ThemeAddonCatalog::PREMIUM,
        ]);
        $instance->forceFill(['price_usd' => 1500.00, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();

        $result = $this->service()->payAndActivate($instance, $payer);

        $this->assertSame(WhiteLabelInstance::ACTIVE, $result->status);
        $this->assertSame('0.0000', (string) $payer->wallet->fresh()->usd_balance, 'the payer must be charged for the license PLUS the theme add-on, not the license alone');

        $this->assertSame(2, WhiteLabelLicensePayment::count());
        $license = WhiteLabelLicensePayment::where('kind', WhiteLabelLicensePayment::KIND_INITIAL)->first();
        $addon = WhiteLabelLicensePayment::where('kind', WhiteLabelLicensePayment::KIND_THEME_ADDON)->first();
        $this->assertSame('1500.00', (string) $license->amount_usd);
        $this->assertSame('4000.00', (string) $addon->amount_usd);

        $this->assertSame(5500.0, app(PlatformEarningsService::class)->balance());
    }

    public function test_a_theme_addon_payment_never_counts_toward_the_balance_to_extended(): void
    {
        // Money-safety regression: a merchant must never be able to pay
        // their way to Extended early just by picking an expensive theme
        // tier — only genuine license payments (initial + balance
        // completion) may count toward that threshold.
        $payer = $this->fundedUser(5500);
        $instance = $this->service()->register([
            'brand_name' => 'NoShortcut Co', 'contact_email' => 'ns@co.test',
            'theme_addon' => ThemeAddonCatalog::PREMIUM, // $4000
        ]);
        $instance->forceFill(['price_usd' => 1500.00, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();

        $this->service()->payAndActivate($instance, $payer);

        // Paid $1500 (license) + $4000 (theme) = $5500 total, but only the
        // $1500 license portion may count toward the Extended threshold.
        $this->assertSame(1500.0, $instance->fresh()->amountPaidTotal());
    }

    public function test_pay_and_activate_with_no_theme_addon_charges_only_the_license_price(): void
    {
        $payer = $this->fundedUser(1500);
        $instance = $this->service()->register(['brand_name' => 'No Addon Co', 'contact_email' => 'na@co.test']);
        $instance->forceFill(['price_usd' => 1500.00, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();

        $this->service()->payAndActivate($instance, $payer);

        $this->assertSame('0.0000', (string) $payer->wallet->fresh()->usd_balance);
        $this->assertSame(1, WhiteLabelLicensePayment::count(), 'no theme add-on chosen means no second ledger row at all');
    }

    // --- priceForPayment(): admin pricing without issuing ---

    public function test_price_for_payment_sets_the_price_without_issuing_a_license(): void
    {
        $admin = User::factory()->create();
        $instance = $this->service()->register(['brand_name' => 'Pending Priced', 'contact_email' => 'pp@co.test']);

        $priced = $this->service()->priceForPayment($instance, 1500.00, $admin->id);

        $this->assertSame('1500.00', (string) $priced->price_usd);
        $this->assertSame($admin->id, $priced->reviewed_by);
        $this->assertSame(WhiteLabelInstance::PENDING, $priced->status);
        $this->assertNull($priced->license_key);
    }

    // --- upgradeTier(): non-destructive ---

    public function test_upgrade_tier_changes_tier_and_entitlement_without_touching_key_or_tokens(): void
    {
        $instance = $this->service()->register(['brand_name' => 'Acme', 'contact_email' => 'a@acme.test']);
        $instance = $this->service()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
        $result = $this->service()->activateWithKey($instance->license_key);
        $token = $result['token'];
        $keyBefore = $instance->fresh()->license_key;

        $upgraded = $this->service()->upgradeTier($instance->fresh(), WhiteLabelInstance::TIER_EXTENDED);

        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $upgraded->tier);
        $this->assertSame(WhiteLabelInstance::LEVEL_FULL, $upgraded->entitlement_level);
        $this->assertSame($keyBefore, $upgraded->license_key);
        $this->assertSame(1, $upgraded->tokens()->count(), 'the live fork token must survive an in-place upgrade');
        $this->assertSame(substr($token, -4), $upgraded->api_token_last_four);
    }

    // --- payAndActivate(): first purchase ---

    public function test_pay_and_activate_charges_the_payer_issues_the_license_and_credits_platform_earnings(): void
    {
        $payer = $this->fundedUser(1500);
        $instance = $this->service()->register(['brand_name' => 'Basic Co', 'contact_email' => 'b@co.test']);
        $instance->forceFill(['price_usd' => 1500.00, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();

        $result = $this->service()->payAndActivate($instance, $payer);

        $this->assertSame(WhiteLabelInstance::ACTIVE, $result->status);
        $this->assertSame(WhiteLabelInstance::TIER_NORMAL, $result->tier);
        $this->assertNotNull($result->license_key);
        $this->assertSame('0.0000', (string) $payer->wallet->fresh()->usd_balance);

        $this->assertSame(1, WhiteLabelLicensePayment::count());
        $payment = WhiteLabelLicensePayment::first();
        $this->assertSame('1500.00', (string) $payment->amount_usd);
        $this->assertSame(WhiteLabelLicensePayment::KIND_INITIAL, $payment->kind);

        $this->assertSame(1500.0, app(PlatformEarningsService::class)->balance());
    }

    public function test_pay_and_activate_refuses_an_unpriced_instance(): void
    {
        $payer = $this->fundedUser();
        $instance = $this->service()->register(['brand_name' => 'Unpriced', 'contact_email' => 'u@co.test']);

        $this->expectException(LicenseActivationException::class);
        $this->service()->payAndActivate($instance, $payer);
    }

    public function test_pay_and_activate_refuses_an_instance_that_already_has_a_live_license(): void
    {
        $payer = $this->fundedUser();
        $instance = $this->service()->register(['brand_name' => 'Already', 'contact_email' => 'x@co.test']);
        $instance = $this->service()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
        $instance->forceFill(['price_usd' => 1500.00])->save();

        $this->expectException(LicenseActivationException::class);
        $this->service()->payAndActivate($instance, $payer);
    }

    public function test_pay_and_activate_never_charges_the_payer_when_delivery_fails(): void
    {
        $this->travelTo(now());
        $payer = $this->fundedUser(1500);
        $instance = $this->service()->register(['brand_name' => 'Broken', 'contact_email' => 'br@co.test']);
        $instance->forceFill(['price_usd' => 1500.00])->save();

        // Pre-occupy the exact reference payAndActivate will generate (time is
        // frozen, so both computations land on the same timestamp) — its unique
        // constraint then blows up the delivery closure deterministically.
        WhiteLabelLicensePayment::create([
            'white_label_instance_id' => $instance->id,
            'amount_usd' => 1500.00,
            'kind' => WhiteLabelLicensePayment::KIND_INITIAL,
            'payment_reference' => 'wl-license:'.$instance->id.':initial:'.now()->timestamp,
        ]);

        try {
            $this->service()->payAndActivate($instance, $payer);
            $this->fail('a duplicate payment_reference should blow up the delivery closure');
        } catch (\Throwable) {
            // expected — either the unique constraint or the wrapped orphan-charge exception
        }

        $this->assertSame('1500.0000', (string) $payer->wallet->fresh()->usd_balance, 'the failed delivery must be auto-refunded');
    }

    // --- payBalanceAndUpgrade(): balance-completion ---

    public function test_pay_balance_and_upgrade_moves_a_normal_instance_to_extended(): void
    {
        $payer = $this->fundedUser(3500);
        $instance = $this->service()->register(['brand_name' => 'Upgrader', 'contact_email' => 'up@co.test']);
        $instance = $this->service()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
        $keyBefore = $instance->license_key;

        $result = $this->service()->payBalanceAndUpgrade($instance, $payer, 3500.00);

        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $result->tier);
        $this->assertSame(WhiteLabelInstance::LEVEL_FULL, $result->entitlement_level);
        $this->assertSame($keyBefore, $result->license_key, 'balance completion must not re-issue the key');
        $this->assertSame('0.0000', (string) $payer->wallet->fresh()->usd_balance);

        $this->assertSame(1, WhiteLabelLicensePayment::where('kind', WhiteLabelLicensePayment::KIND_BALANCE_COMPLETION)->count());
        $this->assertSame(3500.0, app(PlatformEarningsService::class)->balance());
    }

    public function test_pay_balance_and_upgrade_is_a_second_independent_charge_from_the_initial_payment(): void
    {
        $payer = $this->fundedUser(5000);
        $instance = $this->service()->register(['brand_name' => 'FullPath', 'contact_email' => 'fp@co.test']);
        $instance->forceFill(['price_usd' => 1500.00, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();

        $this->service()->payAndActivate($instance->fresh(), $payer);
        $this->assertSame('3500.0000', (string) $payer->wallet->fresh()->usd_balance);

        $this->service()->payBalanceAndUpgrade($instance->fresh(), $payer, 3500.00);
        $this->assertSame('0.0000', (string) $payer->wallet->fresh()->usd_balance);

        $this->assertSame(2, WhiteLabelLicensePayment::count());
        $this->assertSame(5000.0, app(PlatformEarningsService::class)->balance());
        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $instance->fresh()->tier);
    }

    public function test_pay_balance_and_upgrade_refuses_an_instance_without_a_live_license(): void
    {
        $payer = $this->fundedUser();
        $instance = $this->service()->register(['brand_name' => 'Pending', 'contact_email' => 'pe@co.test']);

        $this->expectException(LicenseActivationException::class);
        $this->service()->payBalanceAndUpgrade($instance, $payer, 3500.00);
    }

    public function test_pay_balance_and_upgrade_refuses_an_instance_already_at_extended(): void
    {
        $payer = $this->fundedUser();
        $instance = $this->service()->register(['brand_name' => 'AlreadyExt', 'contact_email' => 'ae@co.test']);
        $instance = $this->service()->issueLicense($instance, WhiteLabelInstance::TIER_EXTENDED);

        $this->expectException(LicenseActivationException::class);
        $this->service()->payBalanceAndUpgrade($instance, $payer, 3500.00);
    }
}
