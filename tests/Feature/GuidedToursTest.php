<?php

namespace Tests\Feature;

use App\Livewire\GuidedTours;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourProgress;
use App\Models\TourStep;
use App\Models\User;
use App\Notifications\EventNotification;
use App\Support\Tours\TourIntegrity;
use App\Support\Tours\TourProgressService;
use App\Support\Tours\TourRegistry;
use App\Support\Tours\TourWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class GuidedToursTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_defaults_are_seeded_idempotently_and_never_overwrite_an_edited_tour(): void
    {
        $this->assertGreaterThanOrEqual(2, Tour::count());
        $n = TourStep::count();
        TourRegistry::sync();
        $this->assertSame($n, TourStep::count());

        $t = Tour::where('key', 'wallet.main')->first();
        $t->update(['title' => 'Mine', 'is_customized' => true]);
        TourRegistry::sync();
        $this->assertSame('Mine', $t->fresh()->title);
        TourRegistry::reset('wallet.main');
        $this->assertSame('Your wallet', $t->fresh()->title);
        $this->assertFalse($t->fresh()->is_customized);
    }

    public function test_tour_integrity_is_green_for_the_shipped_tours_and_red_when_an_anchor_disappears(): void
    {
        $this->assertSame([], TourIntegrity::problems());
        TourStep::where('anchor', 'wallet.balance')->update(['anchor' => 'wallet.gone']);
        $this->assertStringContainsString('wallet.gone', implode(' ', TourIntegrity::problems()));
    }

    public function test_the_json_is_scoped_to_the_signed_in_person_and_quick_mode_returns_only_quick_steps(): void
    {
        $u = $this->user();
        $this->getJson('/tours/wallet.main')->assertUnauthorized();
        $full = $this->actingAs($u)->getJson('/tours/wallet.main')->assertOk()->json();
        $quick = $this->actingAs($u)->getJson('/tours/wallet.main?mode=quick')->assertOk()->json();
        $this->assertGreaterThan(count($quick['steps']), count($full['steps']));
        $this->assertCount(3, $quick['steps']);
        $this->actingAs($u)->getJson('/tours/nope.nothing')->assertNotFound();
        Tour::where('key', 'wallet.main')->update(['is_active' => false]);
        $this->actingAs($u)->getJson('/tours/wallet.main')->assertNotFound();
    }

    public function test_progress_is_saved_resumes_and_a_finished_tour_is_not_offered_again_until_the_version_changes(): void
    {
        Notification::fake();
        $u = $this->user();
        $t = Tour::where('key', 'wallet.main')->first();
        $this->actingAs($u)->postJson('/tours/wallet.main/progress', ['event' => 'started', 'mode' => 'full'])->assertOk();
        $this->actingAs($u)->postJson('/tours/wallet.main/progress', ['event' => 'step', 'step' => 3, 'anchor' => 'wallet.tabs'])->assertOk();
        $this->assertSame(3, TourProgress::where('user_id', $u->id)->first()->last_step);
        $this->assertSame(3, $this->actingAs($u)->getJson('/tours/wallet.main?resume=1')->json('resume_at'));
        $this->assertSame(0, $this->actingAs($u)->getJson('/tours/wallet.main?resume=0')->json('resume_at'));

        $this->assertNotNull(TourProgressService::candidate($u, 'wallet'));   // in progress: still offered
        $this->actingAs($u)->postJson('/tours/wallet.main/progress', ['event' => 'completed'])->assertOk();
        $this->assertNull(TourProgressService::candidate($u, 'wallet'));
        Notification::assertSentTo($u, EventNotification::class, fn ($n) => $n->event === 'tour.completed');

        $t->update(['version' => $t->version + 1]);   // an admin publishes a new version
        $this->assertNotNull(TourProgressService::candidate($u, 'wallet'));
    }

    public function test_dismissing_and_never_show_tips_stop_the_prompts_and_completion_notifies_once(): void
    {
        Notification::fake();
        $u = $this->user();
        $this->actingAs($u)->postJson('/tours/dashboard.main/progress', ['event' => 'dismissed'])->assertOk();
        $this->assertNull(TourProgressService::candidate($u, 'dashboard'));
        $this->actingAs($u)->postJson('/tours/wallet.main/progress', ['event' => 'dismissed', 'never' => true])->assertOk();
        $this->assertFalse(TourWindow::autoEnabled($u));
        $this->assertNull(TourProgressService::candidate($u, 'wallet'));

        $this->actingAs($u)->postJson('/tours/wallet.main/progress', ['event' => 'completed']);
        $this->actingAs($u)->postJson('/tours/wallet.main/progress', ['event' => 'completed']);
        Notification::assertSentToTimes($u, EventNotification::class, 1);
    }

    public function test_the_window_closes_auto_prompts_and_hides_the_hub_unless_the_owner_chose_keep(): void
    {
        $u = $this->user();
        TourWindow::rolloutAt();
        $old = User::factory()->create(['is_active' => true, 'email_verified_at' => now(), 'created_at' => now()->subDays(90)]);
        $this->assertTrue(TourWindow::open($old), 'an old account counts from the rollout date, not its signup date');

        \Illuminate\Support\Carbon::setTestNow(now()->addDays(31));
        $this->assertFalse(TourWindow::open($u));
        $this->assertNull(TourProgressService::candidate($u, 'wallet'));
        $this->actingAs($u)->get('/account/tours')->assertNotFound();
        Setting::setValue('tours.hub_visibility_after_window', 'keep', 'tours');
        $this->actingAs($u)->get('/account/tours')->assertOk()->assertSee('Guided tours');
        Setting::setValue('tours.window_days', 0, 'tours');   // 0 = never expires
        $this->assertTrue(TourWindow::open($u));
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_the_hub_lists_tours_with_status_and_sends_the_person_to_the_entry_page(): void
    {
        $u = $this->user();
        TourProgressService::start($u, Tour::where('key', 'dashboard.main')->first(), 'full');
        $c = Livewire::actingAs($u)->test(GuidedTours::class)->assertSee('Your dashboard')->assertSee('In progress')->assertSee('Quick replay');
        $c->call('start', 'wallet.main', 'quick')->assertRedirect(route('wallet', ['tour' => 'wallet.main', 'mode' => 'quick']));
        $c->set('auto', false);
        $this->assertFalse(TourWindow::autoEnabled($u));
        $c->call('resetAll');
        $this->assertSame(0, TourProgress::where('user_id', $u->id)->count());
    }

    public function test_the_entry_page_carries_the_route_marker_the_engine_reads(): void
    {
        $html = $this->actingAs($this->user())->get('/wallet')->assertOk()->getContent();
        $this->assertStringContainsString('name="nx-route" content="wallet"', $html);
        $this->assertStringContainsString('data-tour="nav.wallet"', $html);
    }

    public function test_tour_studio_edits_reorders_publishes_resets_and_checks(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $admin = $this->user();
        $admin->assignRole('super_admin');
        $t = Tour::where('key', 'wallet.main')->first();
        $first = $t->steps()->orderBy('position')->first();
        $c = Livewire::actingAs($admin)->test(\App\Livewire\Admin\TourStudio::class)->call('edit', $t->id)
            ->set('title', 'Wallet basics')->set("steps.{$first->id}.body", 'Edited text.')->call('save')->assertHasNoErrors();
        $this->assertTrue($t->fresh()->is_customized);
        $this->assertSame('Edited text.', $first->fresh()->body);
        TourRegistry::sync();   // an edited tour survives a code sync
        $this->assertSame('Wallet basics', $t->fresh()->title);

        $c->call('move', $first->id, 'down');
        $this->assertSame(2, $first->fresh()->position);
        $v = $t->fresh()->version;
        $c->call('publish');
        $this->assertSame($v + 1, $t->fresh()->version);
        $c->call('resetDefaults');
        $this->assertSame('Your wallet', $t->fresh()->title);
        $c->call('checkAll')->assertSet('check', []);
        $c->set('window_days', 14)->set('hub_after', 'keep')->call('saveWindow')->assertHasNoErrors();
        $this->assertSame(14, TourWindow::days());
        $this->assertSame('keep', TourWindow::hubAfterWindow());
    }

}
