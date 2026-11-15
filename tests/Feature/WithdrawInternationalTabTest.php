<?php

namespace Tests\Feature;

use App\Livewire\Withdraw;
use App\Models\PayoutCorridor;
use App\Models\User;
use App\Services\Payouts\Extensions\PayoutRailExtensions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Every international rail the guide offers opens the International tab (Grey) with the member's country preselected. */
class WithdrawInternationalTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.grey.api_secret' => 'gbsk_test', 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co']);
        PayoutRailExtensions::flush();
        PayoutRailExtensions::gateways(app());
        PayoutCorridor::create(['country' => 'GH', 'currency' => 'GHS', 'provider' => 'grey', 'method' => 'mobile_money', 'enabled' => true, 'priority' => 100]);
    }

    public function test_the_global_rail_opens_the_international_tab_with_the_country_chosen(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Withdraw::class)
            ->dispatch('payout-rail-chosen', rail: 'global', country: 'GH')
            ->assertSet('accountType', 'grey')->assertSet('greyCountry', 'GH');
    }

    public function test_a_country_grey_does_not_cover_still_opens_the_tab_but_never_preselects_a_wrong_country(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Withdraw::class)
            ->dispatch('payout-rail-chosen', rail: 'global', country: 'NG')
            ->assertSet('accountType', 'grey')->assertSet('greyCountry', '');
    }

    public function test_local_rails_still_open_the_bank_tab_and_the_offer_card_preselects_the_members_country(): void
    {
        Livewire::actingAs(User::factory()->create(['country_code' => 'GH']))->test(Withdraw::class)
            ->dispatch('payout-rail-chosen', rail: 'paystack', country: 'GH')->assertSet('accountType', 'bank')
            ->call('startGrey')->assertSet('accountType', 'grey')->assertSet('greyCountry', 'GH');
    }
}
