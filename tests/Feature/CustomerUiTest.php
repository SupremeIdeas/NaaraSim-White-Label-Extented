<?php

namespace Tests\Feature;

use App\Livewire\Catalogue;
use App\Livewire\Checkout;
use App\Livewire\GetNumber;
use App\Livewire\Wallet as WalletComponent;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

class CustomerUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        // Pin the NGN rate to manual so CurrencyService never makes a live
        // Airalo call while rendering prices in tests.
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
    }

    private function plan(array $extra = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo',
            'provider_plan_id' => 'ui-1',
            'name' => 'USA 3GB 30D',
            'data_mb' => 3072,
            'validity_days' => 30,
            'countries' => ['US'],
            'cost_price_usd' => 3.77,       // PRIVATE — must never render
            'computed_retail_usd' => 10.00, // retail shown to users
        ], $extra))->fresh();
    }

    public function test_display_accessor_formats_usd_and_ngn_and_hides_cost(): void
    {
        $plan = $this->plan();
        $price = $plan->display_price;

        $this->assertSame('$10.00', $price['usd']);
        $this->assertStringStartsWith('NGN ', $price['ngn']);
        $this->assertArrayNotHasKey('cost_price_usd', $plan->toArray());
    }

    public function test_catalogue_shows_retail_never_cost(): void
    {
        $this->plan();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Catalogue::class)
            ->assertSee('$10.00')
            ->assertDontSee('3.77')            // cost value never rendered
            ->assertDontSee('cost_price_usd');
    }

    public function test_checkout_debits_wallet_and_creates_order_via_router(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        // eSIM Go fulfils the order.
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: [
            'iccid' => '8944000', 'orderReference' => 'ORD-1',
        ]));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true) // device-compat gate (Section 32)
            ->call('purchase')
            ->assertSet('done', true)
            ->assertDontSee('3.77'); // still no cost on the success screen

        $this->assertSame('10.0000', (string) $user->wallet->fresh()->usd_balance); // 20 - 10
        $order = EsimOrder::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('8944000', $order->iccid);
        $this->assertSame('10.0000', (string) $order->price_charged);
        // wholesale_cost stored but hidden from serialization
        $this->assertArrayNotHasKey('wholesale_cost', $order->toArray());
    }

    public function test_checkout_blocks_when_wallet_is_too_low(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create(); // no funds

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true) // pass the device gate to reach the balance check
            ->call('purchase')
            ->assertSet('done', false)
            ->assertSet('error', 'Your wallet balance is too low. Please top up and try again.');

        $this->assertSame(0, EsimOrder::count());
    }

    public function test_wallet_topup_initializes_gateway_and_redirects(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'r1']]),
        ]);
        config(['services.paystack.secret_key' => 'sk_test']);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(WalletComponent::class)
            ->set('amount', 5000)
            ->set('gateway', 'paystack')
            ->set('currency', 'NGN')
            ->call('topUp')
            ->assertRedirect('https://checkout.paystack.com/xyz');
    }

    public function test_get_number_debits_orders_and_schedules_the_poll(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        // 5sim serves a Nigeria OTP; Getatext is not in the lane.
        app()->instance('number.fivesim', new FakeSmsProvider(
            price: 0.20,
            buyResponse: ['provider_ref' => '5S-1', 'number' => '2348010000000', 'cost' => 0.20, 'status' => 'pending'],
        ));

        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('country', 'nigeria')
            ->set('service', 'whatsapp')
            ->set('type', 'otp')
            ->call('order')
            ->assertSet('error', null);

        $this->assertDatabaseHas('sms_orders', ['user_id' => $user->id, 'provider' => 'fivesim']);
        Queue::assertPushed(\App\Jobs\PollSmsOtpJob::class);
        // Debited the retail, not zero.
        $this->assertTrue((float) $user->wallet->fresh()->usd_balance < 20.0);
    }

    public function test_customer_routes_require_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/wallet')->assertRedirect('/login');
        $this->get('/numbers')->assertRedirect('/login');
    }

    public function test_customer_views_use_dark_mode_and_loading_states(): void
    {
        $views = glob(resource_path('views/livewire/*.blade.php'));
        $this->assertNotEmpty($views);

        // Every livewire partial's content, concatenated. A page that delegates all
        // its markup to partials (Theme Batch 2 §2 — e.g. dashboard.blade.php) has
        // its dark-mode styling there, so it is verified against this blob.
        $partialBlob = '';
        if (is_dir(resource_path('views/livewire/partials'))) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views/livewire/partials')));
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $partialBlob .= file_get_contents($file->getPathname());
                }
            }
        }

        $actionViews = ['checkout', 'wallet', 'get-number', 'catalogue'];
        foreach ($views as $view) {
            $contents = file_get_contents($view);

            // A page that carries no dark: itself but composes partials delegates
            // its dark styling to them — check the partial blob for those wrappers.
            $delegates = ! str_contains($contents, 'dark:')
                && str_contains($contents, "@include('livewire.partials");
            $haystack = $delegates ? $partialBlob : $contents;
            $this->assertStringContainsString('dark:', $haystack, basename($view).' (or its partials) must have dark: variants');

            if (in_array(pathinfo($view, PATHINFO_FILENAME), $actionViews, true)) {
                $this->assertStringContainsString('wire:loading', $contents, basename($view).' must show a loading state');
            }
        }
    }
}
