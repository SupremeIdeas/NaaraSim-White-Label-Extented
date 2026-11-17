<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Models\TopupProvider;
use App\Services\Topup\TopupCatalogue;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeTopupProvider;
use Tests\TestCase;

/** A supplier that refuses this server is tried three times, then skipped with ONE clear alert, instead of 200 failed calls and 200 warnings. */
class TopupSyncRefusalTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_refusals_in_a_row_stop_that_supplier_and_raise_one_alert_naming_the_ip(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Cache::flush();
        Bus::fake();
        $calls = 0;
        $ding = new class('ding') extends FakeTopupProvider {
            public int $calls = 0;

            public function countries(): array
            {
                return ['AO', 'BF', 'BJ', 'AT', 'BM', 'BR', 'CA', 'DE'];
            }

            public function listProducts(string $countryIso): array
            {
                $this->calls++;

                throw new \RuntimeException('HTTP request returned status code 401: {"ErrorCodes":[{"Code":"AuthenticationFailed"}]}');
            }
        };
        foreach (['zendit', 'vtpass', 'reloadly', 'bitrefill', 'paga'] as $c) {
            $this->app->instance('topup.provider.'.$c, new FakeTopupProvider($c, configured: false));
        }
        $this->app->instance('topup.provider.ding', $ding);
        app(\App\Services\Topup\TopupOps::class)->seed();
        TopupProvider::where('code', 'ding')->update(['enabled' => true]);

        app(TopupCatalogue::class)->syncAll();

        $this->assertSame(3, $ding->calls, 'stops after three refusals in a row');
        Bus::assertDispatchedTimes(AlertAdminJob::class, 1);
        Bus::assertDispatched(AlertAdminJob::class, fn ($j) => str_contains($j->message, 'refused this server') && str_contains($j->message, 'allow-list'));
        $this->assertNull(TopupProvider::where('code', 'ding')->value('last_synced_at'), 'a supplier that never answered is not marked as synced');
    }

    public function test_an_ordinary_failure_is_not_mistaken_for_a_refusal(): void
    {
        $this->assertTrue(TopupCatalogue::isAuthRefusal('HTTP request returned status code 401: AuthenticationFailed'));
        $this->assertTrue(TopupCatalogue::isAuthRefusal('403 Forbidden'));
        $this->assertFalse(TopupCatalogue::isAuthRefusal('cURL error 28: Operation timed out'));
        $this->assertFalse(TopupCatalogue::isAuthRefusal('HTTP request returned status code 500'));
    }
}
