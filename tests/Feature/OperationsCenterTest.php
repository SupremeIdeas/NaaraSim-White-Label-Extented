<?php

namespace Tests\Feature;

use App\Livewire\Admin\Nci\ProviderDetail;
use App\Livewire\Admin\Nci\ProviderRegistry as ProviderRegistryPage;
use App\Livewire\Admin\Nci\RoutingConsole;
use App\Models\ProviderRegistry;
use App\Models\User;
use App\Services\Routing\CircuitBreaker;
use App\Support\RoutingPreference;
use Database\Seeders\ProviderRegistrySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA-BUILD-17 — the NCI Operations Center. Read-only visibility over Layers
 * 1–3 plus gated override actions that call the existing services directly.
 */
class OperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ProviderRegistrySeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_registry_groups_by_family_with_real_provider_names(): void
    {
        Livewire::actingAs($this->admin())->test(ProviderRegistryPage::class)
            ->assertOk()
            ->assertSee('Provider Registry')
            ->assertSee('Naara Data')      // family grouping
            ->assertSee('esimgo')          // real provider name, never masked
            ->assertSee('twilio');
    }

    public function test_the_pages_are_gated_to_nci_view(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ProviderRegistryPage::class)->assertStatus(403);
    }

    public function test_provider_detail_shows_the_dashboard_link_and_gates_the_reveal(): void
    {
        Livewire::actingAs($this->admin())->test(ProviderDetail::class, ['provider' => 'twilio'])
            ->assertOk()
            ->assertSee('Open Provider Dashboard')
            ->assertSee('https://console.twilio.com/', false)
            ->set('revealPassword', 'wrong-password')
            ->call('reveal')
            ->assertHasErrors('revealPassword');
    }

    public function test_manual_circuit_open_calls_the_breaker(): void
    {
        Livewire::actingAs($this->admin())->test(ProviderDetail::class, ['provider' => 'esimgo'])
            ->call('openCircuit')
            ->assertDispatched('nx-toast');

        $this->assertSame(CircuitBreaker::OPEN, ProviderRegistry::where('provider_key', 'esimgo')->value('circuit_breaker_state'));
    }

    public function test_routing_console_simulates_order_and_sets_a_preference(): void
    {
        Livewire::actingAs($this->admin())->test(RoutingConsole::class)
            ->assertOk()
            ->assertSee('Routing Console')
            ->set('prefStack', 'esim')
            ->set('prefProvider', 'airalo')
            ->set('prefHours', 3)
            ->call('setPreference')
            ->assertDispatched('nx-toast');

        $this->assertSame('airalo', RoutingPreference::preferred('esim'));
    }

    public function test_a_set_preference_floats_the_provider_to_the_front(): void
    {
        // Give the snapshot rows so ordering runs, then prefer the last one.
        RoutingPreference::prefer('esim', 'quibity', 2);
        ProviderRegistry::flushSnapshot();

        $ordered = app(\App\Services\Routing\CandidateOrdering::class)->order(['esimgo', 'airalo', 'quibity'], 'esim');
        $this->assertSame('quibity', $ordered[0]);
    }
}
