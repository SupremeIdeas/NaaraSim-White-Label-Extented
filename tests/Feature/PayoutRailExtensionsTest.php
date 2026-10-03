<?php

namespace Tests\Feature;

use App\Models\PayoutCorridor;
use App\Models\PayoutCountryRail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Extensions\PayoutRailExtensions;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Services\Payouts\Rail\RailAdvisor;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Updater-delivered rails: Payoneer/Grey/Stripe Global arrive later as a package. Until then they are "coming soon",
 * a broken or hostile extension can never take a core rail down, and the owner can switch one off.
 */
class PayoutRailExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        $this->dir = sys_get_temp_dir().'/rails-'.uniqid();
        File::makeDirectory($this->dir, 0777, true);
        config(['payouts.extensions_path' => $this->dir, 'services.paystack.secret_key' => 'sk_p']);
        $this->reload();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        PayoutRailExtensions::flush();
        parent::tearDown();
    }

    private function reload(): void
    {
        PayoutRailExtensions::flush();
        app()->forgetInstance(PayoutService::class);
        app(PayoutRailRegistry::class)->flush();
    }

    private function extension(string $slug, array $manifest = [], ?string $gatewayCode = null, string $provider = 'toyrail'): void
    {
        $class = 'Toy'.ucfirst($slug).uniqid();
        $code = $gatewayCode ?? <<<PHP
<?php
namespace App\\PayoutRails\\Fixture;
use App\\Models\\PayoutAccount;
use App\\Models\\PayoutRequest;
use App\\Services\\Payouts\\{DeclaresCapabilities, PayoutEvent, PayoutGatewayInterface, PayoutTransferResult};
use Illuminate\\Http\\Request;
class {$class} implements PayoutGatewayInterface, DeclaresCapabilities {
    public function name(): string { return '{$provider}'; }
    public function available(): bool { return true; }
    public function createRecipient(PayoutAccount \$a): string { return 'r'; }
    public function sendTransfer(PayoutRequest \$r, PayoutAccount \$a): PayoutTransferResult { return new PayoutTransferResult(status: 'processing', providerRef: 'x'); }
    public function verifyWebhook(Request \$r): bool { return false; }
    public function parseWebhook(Request \$r): ?PayoutEvent { return null; }
    public function capabilities(): array { return ['confirms_synchronously' => false, 'webhook' => false, 'lookup' => false, 'cancel' => false]; }
}
PHP;
        File::makeDirectory("{$this->dir}/{$slug}", 0777, true);
        File::put("{$this->dir}/{$slug}/Gateway.php", $code);
        File::put("{$this->dir}/{$slug}/rail.json", json_encode(array_merge([
            'slug' => $slug, 'label' => ucfirst($slug), 'provider' => $provider, 'version' => '1.0.0',
            'gateway' => 'App\\PayoutRails\\Fixture\\'.$class, 'files' => ['Gateway.php'],
        ], $manifest)));
    }

    public function test_with_nothing_installed_every_planned_rail_is_coming_soon_and_core_payouts_are_untouched(): void
    {
        $status = collect(PayoutRailExtensions::catalogue())->pluck('status', 'provider');

        $this->assertSame('coming_soon', $status['payoneer']);
        $this->assertSame('coming_soon', $status['grey']);
        $this->assertSame('coming_soon', $status['stripe_global']);
        $this->assertNull(app(PayoutService::class)->gatewayFor('payoneer'));
        $this->assertNotNull(app(PayoutService::class)->gatewayFor('paystack'));
        $this->assertSame([], PayoutRailExtensions::problems());
    }

    public function test_an_enabled_corridor_for_an_uninstalled_rail_is_coming_soon_never_offered_and_never_blocks_paystack(): void
    {
        PayoutCountryRail::updateOrCreate(['country' => 'NG', 'rail' => 'paystack'], ['provider_supports' => true, 'admin_override' => 'none', 'verified_at' => now(), 'verified_by' => 'seed']);
        PayoutCorridor::updateOrCreate(['country' => 'NG', 'currency' => 'NGN', 'provider' => 'paystack', 'method' => 'bank'], ['enabled' => true, 'priority' => 10]);
        PayoutCountryRail::updateOrCreate(['country' => 'BR', 'rail' => 'global'], ['provider_supports' => true, 'admin_override' => 'none', 'verified_at' => now(), 'verified_by' => 'seed']);
        PayoutCorridor::updateOrCreate(['country' => 'BR', 'currency' => 'USD', 'provider' => 'payoneer', 'method' => 'payoneer'], ['enabled' => true, 'priority' => 10]);
        $this->reload();

        $registry = app(PayoutRailRegistry::class);
        $this->assertSame('available', $registry->state('NG', 'paystack'));
        $this->assertSame('coming_soon', $registry->state('BR', 'global'));

        $advice = app(RailAdvisor::class)->advise(User::factory()->create(['country_code' => 'NG']), 'NG');
        $this->assertSame('fast_available', $advice['verdict']);
        $this->assertSame('paystack', $advice['options'][0]['rail']);

        $brazil = app(RailAdvisor::class)->advise(User::factory()->create(['country_code' => 'BR']), 'BR');
        $this->assertNotContains($brazil['verdict'], ['global_only', 'fast_available']);
    }

    public function test_an_installed_extension_registers_with_the_engine_and_makes_its_corridor_available(): void
    {
        $this->extension('toyrail');
        PayoutCountryRail::updateOrCreate(['country' => 'BR', 'rail' => 'global'], ['provider_supports' => true, 'admin_override' => 'none', 'verified_at' => now(), 'verified_by' => 'seed']);
        PayoutCorridor::updateOrCreate(['country' => 'BR', 'currency' => 'USD', 'provider' => 'toyrail', 'method' => 'bank'], ['enabled' => true, 'priority' => 10]);
        config(['payouts.global_rail_providers' => ['payoneer', 'grey', 'stripe_global', 'manual_external', 'toyrail']]);
        $this->reload();

        $this->assertTrue(PayoutRailExtensions::installed('toyrail'));
        $this->assertSame('toyrail', app(PayoutService::class)->gatewayFor('toyrail')?->name());
        $this->assertSame('toyrail', app('payout.toyrail')->name());
        $this->assertSame('available', app(PayoutRailRegistry::class)->state('BR', 'global'));
        $this->assertContains('toyrail', collect(PayoutRailExtensions::catalogue())->pluck('provider')->all());
    }

    public function test_an_extension_cannot_take_a_core_provider_name(): void
    {
        $this->extension('evil', provider: 'paystack');
        $this->reload();

        $this->assertFalse(PayoutRailExtensions::installed('evil'));
        $this->assertStringContainsString('core provider', PayoutRailExtensions::problems()[0]['message']);
        $this->assertSame('paystack', app(PayoutService::class)->gatewayFor('paystack')?->name());
        $this->assertSame(\App\Services\Payouts\PaystackPayoutGateway::class, get_class(app(PayoutService::class)->gatewayFor('paystack')));
    }

    public function test_a_broken_extension_is_skipped_and_reported_and_core_keeps_working(): void
    {
        $this->extension('broken', gatewayCode: '<?php this is not php');
        $this->extension('wrongname', ['provider' => 'wrongname'], provider: 'something_else');
        $this->reload();

        $this->assertFalse(PayoutRailExtensions::installed('broken'));
        $service = app(PayoutService::class); // building the engine must not throw
        $this->assertNotNull($service->gatewayFor('paystack'));
        $this->assertNull($service->gatewayFor('wrongname'));
        $slugs = array_column(PayoutRailExtensions::problems(), 'slug');
        $this->assertContains('broken', $slugs);
        $this->assertContains('wrongname', $slugs);
    }

    public function test_an_extension_that_does_not_implement_the_contract_is_refused(): void
    {
        $this->extension('wrongshape', gatewayCode: '<?php namespace App\PayoutRails\Fixture; class NotAGateway {}', manifest: ['gateway' => 'App\\PayoutRails\\Fixture\\NotAGateway']);
        $this->reload();

        $this->assertFalse(PayoutRailExtensions::installed('toyrail'));
        $this->assertStringContainsString('must implement', PayoutRailExtensions::problems()[0]['message']);
    }

    public function test_a_listed_file_outside_the_rail_folder_is_refused(): void
    {
        $this->extension('escape', ['files' => ['../../../../etc/hosts']]);
        $this->reload();

        $this->assertFalse(PayoutRailExtensions::installed('toyrail'));
        $this->assertStringContainsString('outside the rail folder', PayoutRailExtensions::problems()[0]['message']);
    }

    public function test_the_owner_can_switch_an_installed_rail_off_without_deleting_it(): void
    {
        $this->extension('toyrail');
        $this->reload();
        $this->assertTrue(PayoutRailExtensions::installed('toyrail'));

        Setting::setValue(PayoutSettings::DISABLED_EXTENSIONS, 'toyrail', 'payouts');
        $this->reload();

        $this->assertFalse(PayoutRailExtensions::installed('toyrail'));
        $this->assertNull(app(PayoutService::class)->gatewayFor('toyrail'));
    }

    public function test_the_health_page_lists_the_planned_rails_as_coming_soon(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Livewire\Admin\PayoutHealth::class)
            ->assertSee('Payoneer')->assertSee('Grey')->assertSee('Stripe Global Payouts')->assertSee('Coming soon');
    }
}
