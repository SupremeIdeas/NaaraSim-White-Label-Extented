<?php

namespace Tests\Feature;

use App\PayoutRails\Grey\GreyClient;
use App\Services\Payouts\Extensions\PayoutRailExtensions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Grey is part of every install: a fresh checkout shows it installed (never "coming soon") and picks its host from the payout mode. */
class GreyShipsBuiltInTest extends TestCase
{
    use RefreshDatabase;

    public function test_grey_is_installed_on_a_fresh_checkout(): void
    {
        PayoutRailExtensions::flush();
        $this->assertSame([], PayoutRailExtensions::problems());
        $this->assertTrue(PayoutRailExtensions::installed('grey'));
        $row = collect(PayoutRailExtensions::catalogue())->firstWhere('provider', 'grey');
        $this->assertSame('installed', $row['status']);
    }

    public function test_base_url_follows_the_payout_mode_when_not_set(): void
    {
        PayoutRailExtensions::flush();
        PayoutRailExtensions::manifests(); // loads the rail's files (the folder is lower-case, so it is not PSR-4 autoloaded)
        config(['services.grey.base_url' => null]);
        $this->assertSame(GreyClient::SANDBOX, (new GreyClient)->baseUrl(), 'a test box never moves real money');
        config(['app.url' => 'https://naara.app', 'payouts.env' => 'auto']);
        $this->assertSame(GreyClient::PRODUCTION, (new GreyClient)->baseUrl());
        config(['app.url' => 'http://localhost']);
        config(['services.grey.base_url' => 'https://example.test/']);
        $this->assertSame('https://example.test', (new GreyClient)->baseUrl());
    }
}
