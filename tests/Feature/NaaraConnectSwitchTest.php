<?php

namespace Tests\Feature;

use App\Livewire\Catalogue;
use App\Models\EsimPlan;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Support\NaaraConnect;
use App\Support\ProviderModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Naara Connect (Data + Calls): off until something can deliver it, auto-on when keys/voice plans appear, admin switch always wins. */
class NaaraConnectSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function keyGigs(): void
    {
        config(['services.gigs.api_key' => 'k', 'services.gigs.project' => 'p']);
    }

    private function voicePlan(): EsimPlan
    {
        return EsimPlan::create(['provider' => 'zendit', 'provider_plan_id' => 'V1', 'name' => 'Calls 5GB', 'type' => 'Voice + Data', 'has_voice' => true, 'data_mb' => 5120, 'validity_days' => 30, 'countries' => ['FR'], 'cost_price_usd' => 10, 'is_active' => true]);
    }

    public function test_it_is_off_with_no_voice_supplier_and_the_tab_is_hidden(): void
    {
        $this->assertFalse(NaaraConnect::live());
        $this->assertSame('needs_key', ProviderModels::status('naara_connect'), 'a Zendit gift/data key alone does not make Connect live');
        config(['services.zendit.api_key' => 'z']);
        $this->assertFalse(NaaraConnect::live());
        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)->assertDontSee('Data + Calls')->call('setTab', 'full')->assertSet('tab', 'data');
    }

    public function test_it_switches_itself_on_when_a_full_esim_provider_is_keyed_and_the_admin_can_override(): void
    {
        $this->keyGigs();
        $this->assertTrue(NaaraConnect::live(), 'auto-on the moment Gigs keys exist');
        $this->assertSame('live', ProviderModels::status('naara_connect'));
        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)->assertSee('Data + Calls')->call('setTab', 'full')->assertSet('tab', 'full');

        FeatureFlags::setEnabled('naara_connect', false);   // the admin's explicit choice wins over auto
        $this->assertFalse(NaaraConnect::live());
        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)->assertDontSee('Data + Calls');

        FeatureFlags::setEnabled('naara_connect', true);
        $this->assertTrue(NaaraConnect::live());
    }

    public function test_forcing_it_on_without_a_supplier_stays_hidden_until_something_can_deliver_it(): void
    {
        FeatureFlags::setEnabled('naara_connect', true);
        $this->assertFalse(NaaraConnect::live());
        $this->assertTrue(FeatureFlags::adminEnabled('naara_connect'));

        $this->voicePlan();   // a synced voice plan (a Zendit account with voice enabled) is also enough
        \Illuminate\Support\Facades\Cache::forget('naara_connect.voice_plans');
        $this->assertTrue(NaaraConnect::live());
    }
}
