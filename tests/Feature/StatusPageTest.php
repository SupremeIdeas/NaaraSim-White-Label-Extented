<?php

namespace Tests\Feature;

use App\Livewire\Admin\Incidents as AdminIncidents;
use App\Livewire\Admin\SiteChromePage;
use App\Livewire\StatusPage as PublicStatus;
use App\Models\Incident;
use App\Models\Setting;
use App\Models\StatusSubscriber;
use App\Models\User;
use App\Support\SiteChrome;
use App\Support\StatusPage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Public status page + login-treatment toggle. Key guarantee: the public status
 * page NEVER names a supplier (money-safety scrub rule 1.2) — it fronts branded
 * service groups only.
 */
class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_status_page_is_public_and_hides_supplier_names(): void
    {
        $res = $this->get('/status')->assertOk();

        // Branded groups are shown…
        $res->assertSee('eSIM Data network');
        $res->assertSee('Developer API');
        // …but real supplier brands never leak.
        foreach (['esimgo', 'Airalo', 'Twilio', '5sim', 'Telnyx', 'Zendit'] as $supplier) {
            $res->assertDontSee($supplier);
        }
    }

    public function test_an_open_critical_incident_degrades_the_component_and_overall(): void
    {
        $this->assertSame('operational', StatusPage::overall());

        Incident::create([
            'title' => 'Data outage', 'component' => 'data', 'impact' => 'critical',
            'status' => 'investigating', 'started_at' => now(),
        ]);

        $data = collect(StatusPage::components())->firstWhere('key', 'data');
        $this->assertSame('major_outage', $data['state']);
        $this->assertSame('major_outage', StatusPage::overall());
    }

    public function test_visitors_can_subscribe_to_updates(): void
    {
        Livewire::test(PublicStatus::class)
            ->set('email', 'dev@example.com')
            ->call('subscribe')
            ->assertSet('subscribed', true);

        $this->assertDatabaseHas('status_subscribers', ['email' => 'dev@example.com', 'is_active' => true]);
    }

    public function test_admin_can_post_and_resolve_an_incident(): void
    {
        Livewire::actingAs(User::factory()->create())->test(AdminIncidents::class)->assertStatus(403);

        $c = Livewire::actingAs($this->admin())->test(AdminIncidents::class)
            ->set('title', 'Numbers slow')->set('component', 'numbers')
            ->set('impact', 'major')->set('body', 'Investigating latency.')
            ->call('create')->assertHasNoErrors();

        $incident = Incident::firstOrFail();
        $this->assertSame('numbers', $incident->component);

        $c->set('update.'.$incident->id.'.status', 'resolved')
            ->set('update.'.$incident->id.'.body', 'Fixed.')
            ->call('postUpdate', $incident->id);

        $this->assertTrue($incident->fresh()->isResolved());
        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_login_treatment_toggle_persists(): void
    {
        $this->assertSame('auto', SiteChrome::authPanel()['style']);

        Livewire::actingAs($this->admin())->test(SiteChromePage::class)
            ->set('login_style', 'webgl')
            ->set('headline', 'Hi')->set('subtext', 'There')
            ->call('save')->assertHasNoErrors();

        SiteChrome::flush();
        $this->assertSame('webgl', Setting::getValue('site.auth.style'));
    }
}
