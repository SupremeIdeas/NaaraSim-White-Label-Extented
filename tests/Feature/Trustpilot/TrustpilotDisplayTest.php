<?php

namespace Tests\Feature\Trustpilot;

use App\Models\Setting;
use App\Support\Trustpilot\TrustpilotSettings as TP;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustpilotDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function turnOn(array $extra = []): void
    {
        TP::put('enabled', true);
        TP::put('business_unit_id', '4f3e2d1c0b9a8f7e6d5c4b3a');
        TP::put('profile_url', 'https://www.trustpilot.com/review/example.com');
        foreach ($extra as $k => $v) {
            TP::put($k, $v);
        }
    }

    public function test_off_by_default_renders_nothing_and_page_is_404(): void
    {
        $this->get('/reviews')->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('trustpilot', false);
    }

    public function test_on_with_placement_renders_profile_link_and_adds_csp_origins(): void
    {
        $this->turnOn(['placements' => ['auth.badge' => true]]);
        $res = $this->get('/login')->assertOk();
        $res->assertSee('See our reviews on Trustpilot', false);
        $csp = (string) $res->headers->get('Content-Security-Policy');
        if ($csp !== '') {
            $this->assertStringContainsString('https://widget.trustpilot.com', $csp);
        }
    }

    public function test_template_id_renders_official_trustbox_with_business_unit(): void
    {
        $this->turnOn(['placements' => ['auth.badge' => true], 'templates' => ['micro_combo' => '5419b6a8b0d04a076446a9ad']]);
        $this->get('/login')->assertSee('data-template-id="5419b6a8b0d04a076446a9ad"', false)->assertSee('data-businessunit-id="4f3e2d1c0b9a8f7e6d5c4b3a"', false);
    }

    public function test_reviews_page_needs_the_page_switch(): void
    {
        $this->turnOn();
        $this->get('/reviews')->assertNotFound();
        TP::put('page_enabled', true);
        $this->get('/reviews')->assertOk()->assertSee('trustpilot.com/review/example.com', false);
    }

    public function test_admin_panel_saves_settings(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\TrustpilotPanel::class)
            ->set('enabled', true)->set('businessUnitId', '4f3e2d1c0b9a8f7e6d5c4b3a')->call('save')->assertHasNoErrors();
        $this->assertTrue(TP::enabled());
        $this->assertSame('4f3e2d1c0b9a8f7e6d5c4b3a', TP::businessUnitId());
    }

    public function test_invite_script_needs_an_admin_key_and_never_loads_behind_click_to_load(): void
    {
        $this->turnOn();
        $this->get('/login')->assertDontSee('invitejs.trustpilot.com', false);   // no key on a white label until the admin pastes one
        TP::put('integration_key', 'ZZZZ1111yyyy2222');
        $res = $this->get('/login')->assertSee('https://invitejs.trustpilot.com/tp.min.js', false)->assertSee('ZZZZ1111yyyy2222', false);
        $csp = (string) $res->headers->get('Content-Security-Policy');
        if ($csp !== '') {
            $this->assertStringContainsString('https://invitejs.trustpilot.com', $csp);
        }
        TP::put('click_to_load', true);
        $this->get('/login')->assertDontSee('invitejs.trustpilot.com', false);
    }

    private function adminPanel()
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        return \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\TrustpilotPanel::class);
    }

    public function test_an_admin_pastes_their_own_integration_key_and_it_is_the_one_served(): void
    {
        $this->turnOn();
        $this->adminPanel()->assertSet('integrationKey', '')->set('integrationKey', 'NewKey12345678')->call('save')->assertHasNoErrors();
        auth()->logout();
        $this->get('/login')->assertSee("tp('register', \"NewKey12345678\")", false);
    }

    public function test_a_bad_key_is_rejected_by_the_panel_and_never_saved(): void
    {
        $this->adminPanel()->set('integrationKey', "x'); alert(1);//")->call('save')->assertHasErrors(['integrationKey']);
        $this->assertSame('', TP::integrationKey());
    }

    public function test_the_script_is_in_the_head_once_and_no_key_ships_with_the_code(): void
    {
        $this->turnOn();
        $this->get('/')->assertDontSee('invitejs.trustpilot.com', false);
        TP::put('integration_key', 'ZZZZ1111yyyy2222');
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'invitejs.trustpilot.com'));
        $this->assertSame(1, substr_count($html, "tp('register'"));
        $this->assertFalse(class_exists(\App\Support\MasterMarketing\NaaraTrustpilotProfile::class), 'no master preset class exists here');
    }
}
