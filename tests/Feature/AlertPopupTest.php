<?php

namespace Tests\Feature;

use App\Livewire\AlertPopup;
use App\Livewire\Admin\Alerts as AdminAlerts;
use App\Models\Alert;
use App\Models\AlertView;
use App\Models\User;
use App\Services\AlertService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Login notice pop-ups: eligibility (audience, window, view cap, dismissal),
 * the pop-up component, and the admin CRUD with XSS-safe rich text.
 */
class AlertPopupTest extends TestCase
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

    private function alert(array $attrs = []): Alert
    {
        return Alert::create(array_merge([
            'title' => 'Welcome', 'body' => '<p>Hi</p>', 'audience' => 'all',
            'new_days' => 14, 'trigger' => 'any', 'max_views' => 1, 'is_active' => true, 'priority' => 0,
        ], $attrs));
    }

    public function test_an_active_alert_is_eligible_for_a_user(): void
    {
        $alert = $this->alert();
        $user = User::factory()->create();

        $this->assertSame($alert->id, app(AlertService::class)->nextFor($user)?->id);
    }

    public function test_audience_targeting_new_vs_old(): void
    {
        $this->alert(['audience' => 'new', 'new_days' => 7]);

        $fresh = User::factory()->create(['created_at' => now()->subDay()]);
        $veteran = User::factory()->create(['created_at' => now()->subMonths(3)]);

        $this->assertNotNull(app(AlertService::class)->nextFor($fresh));
        $this->assertNull(app(AlertService::class)->nextFor($veteran));
    }

    public function test_view_cap_stops_showing_after_the_limit(): void
    {
        $alert = $this->alert(['max_views' => 2]);
        $user = User::factory()->create();
        $svc = app(AlertService::class);

        $svc->recordView($alert, $user);
        $this->assertNotNull($svc->nextFor($user)); // 1 view, cap 2 → still eligible
        $svc->recordView($alert, $user);
        $this->assertNull($svc->nextFor($user));    // 2 views → capped
    }

    public function test_dismissal_hides_it_forever(): void
    {
        $alert = $this->alert(['max_views' => 0]); // unlimited
        $user = User::factory()->create();
        $svc = app(AlertService::class);

        $svc->dismiss($alert, $user);
        $this->assertNull($svc->nextFor($user));
    }

    public function test_a_notice_outside_its_window_is_not_shown(): void
    {
        $this->alert(['ends_at' => now()->subDay()]);
        $user = User::factory()->create();

        $this->assertNull(app(AlertService::class)->nextFor($user));
    }

    public function test_the_popup_component_records_a_view_and_dismisses(): void
    {
        $alert = $this->alert(['max_views' => 3]);
        $user = User::factory()->create(['is_active' => true]);

        $c = Livewire::actingAs($user)->test(AlertPopup::class)->assertSet('alertId', $alert->id);
        $this->assertSame(1, AlertView::where('alert_id', $alert->id)->where('user_id', $user->id)->first()->views);

        $c->call('dismiss')->assertSet('alertId', null);
        $this->assertNotNull(AlertView::where('alert_id', $alert->id)->where('user_id', $user->id)->first()->dismissed_at);
    }

    public function test_admin_crud_is_gated_and_sanitizes_the_body(): void
    {
        Livewire::actingAs(User::factory()->create())->test(AdminAlerts::class)->assertStatus(403);

        Livewire::actingAs($this->admin())->test(AdminAlerts::class)
            ->set('form.title', 'Big offer')
            ->set('form.body', '<p>Save now</p><script>alert(1)</script>')
            ->set('form.audience', 'all')->set('form.trigger', 'any')
            ->set('form.new_days', 14)->set('form.max_views', 1)->set('form.priority', 0)
            ->call('save')->assertHasNoErrors();

        $alert = Alert::firstOrFail();
        $this->assertStringContainsString('Save now', $alert->body);
        $this->assertStringNotContainsString('<script', $alert->body);
    }
}
