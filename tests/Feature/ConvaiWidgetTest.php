<?php

namespace Tests\Feature;

use App\Livewire\Admin\Integrations;
use App\Models\Setting;
use App\Models\User;
use App\Support\ConvaiWidget;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task #17 — ElevenLabs Convai voice widget: admin-gated, CSP widened only while
 * active, WhatsApp fallback when off.
 */
class ConvaiWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function enableConvai(string $id = 'agent_test1234', string $placement = 'both'): void
    {
        Setting::setValue('convai.enabled', true);
        Setting::setValue('convai.agent_id', $id);
        Setting::setValue('convai.placement', $placement);
        ConvaiWidget::flush();
    }

    public function test_inactive_by_default_renders_nothing_and_does_not_widen_csp(): void
    {
        $this->assertFalse(ConvaiWidget::active());
        $this->assertStringNotContainsString('elevenlabs', Blade::render('<x-convai-widget context="marketing" />'));

        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('elevenlabs.io', (string) $csp);
        $this->assertStringNotContainsString('unpkg.com', (string) $csp);
    }

    public function test_active_widget_renders_the_embed_and_widens_csp_precisely(): void
    {
        $this->enableConvai('agent_abc123');

        $html = Blade::render('<x-convai-widget context="marketing" />');
        $this->assertStringContainsString('<elevenlabs-convai agent-id="agent_abc123">', $html);
        $this->assertStringContainsString('unpkg.com/@elevenlabs/convai-widget-embed', $html);

        $csp = (string) $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
        // Widened in exactly the directives the embed needs.
        $this->assertMatchesRegularExpression('/script-src[^;]*unpkg\.com/', $csp);
        $this->assertMatchesRegularExpression('/script-src[^;]*\*\.elevenlabs\.io/', $csp);
        $this->assertMatchesRegularExpression('/connect-src[^;]*wss:\/\/\*\.elevenlabs\.io/', $csp);
        $this->assertStringContainsString('worker-src', $csp); // appended (was absent)
    }

    public function test_placement_controls_where_it_shows(): void
    {
        $this->enableConvai('agent_x99999', 'marketing');
        $this->assertTrue(ConvaiWidget::shownOn('marketing'));
        $this->assertFalse(ConvaiWidget::shownOn('customer'));
    }

    public function test_id_is_sanitized_and_enable_requires_an_id(): void
    {
        // Junk characters are stripped from the agent id.
        $this->assertSame('agentABC_1-2', ConvaiWidget::sanitizeId('agent<ABC>_1-2 "'));

        // Can't enable without an id.
        Livewire::actingAs($this->admin())->test(Integrations::class)
            ->set('convaiEnabled', true)
            ->set('convaiAgentId', '')
            ->call('saveConvai')
            ->assertHasErrors('convaiAgentId');
        $this->assertFalse(ConvaiWidget::active());
    }

    public function test_admin_can_save_and_it_goes_live(): void
    {
        Livewire::actingAs($this->admin())->test(Integrations::class)
            ->set('convaiEnabled', true)
            ->set('convaiAgentId', 'agent_live0001')
            ->set('convaiPlacement', 'customer')
            ->call('saveConvai')
            ->assertHasNoErrors();

        $this->assertTrue(ConvaiWidget::active());
        $this->assertSame('agent_live0001', ConvaiWidget::agentId());
        $this->assertSame('customer', ConvaiWidget::placement());
    }
}
