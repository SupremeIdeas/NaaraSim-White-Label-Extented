<?php

namespace Tests\Feature;

use App\Jobs\Topup\RefreshTopupCountryJob;
use App\Jobs\Topup\WarmTopupCountriesJob;
use App\Services\Topup\TopupCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** The Top-Up page never waits on a supplier: stale prices are refreshed by a queued job, once per country, and the stored catalogue is served at once. */
class TopupNoLiveCallInRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_serves_stored_prices_and_queues_exactly_one_refresh(): void
    {
        Queue::fake();
        Http::fake();
        $cat = app(TopupCatalogue::class);

        $cat->products('NG');
        $cat->products('NG');
        $cat->operators('NG');

        Queue::assertPushed(RefreshTopupCountryJob::class, 1);
        Http::assertNothingSent();
        $this->assertTrue($cat->refreshing('NG'));
    }

    public function test_a_fresh_country_queues_nothing_and_the_countries_list_never_calls_a_supplier(): void
    {
        Queue::fake();
        Http::fake();
        Cache::put('topup.live.NG', ['vtpass'], 60);
        $cat = app(TopupCatalogue::class);

        $this->assertFalse($cat->refreshInBackground('NG'));
        $cat->countries();

        Queue::assertNotPushed(RefreshTopupCountryJob::class);
        Http::assertNothingSent();
        $this->assertFalse($cat->refreshing('NG'));
    }

    public function test_the_refresh_job_is_unique_per_country(): void
    {
        $this->assertSame((new RefreshTopupCountryJob('ng'))->uniqueId(), (new RefreshTopupCountryJob('NG'))->uniqueId());
        $this->assertNotSame((new RefreshTopupCountryJob('NG'))->uniqueId(), (new RefreshTopupCountryJob('GH'))->uniqueId());
        $this->assertTrue((new WarmTopupCountriesJob) instanceof \Illuminate\Contracts\Queue\ShouldBeUnique);
    }
}
