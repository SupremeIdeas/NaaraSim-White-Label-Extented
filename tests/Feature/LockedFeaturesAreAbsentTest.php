<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\FeatureEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A feature the licence does not include must be ABSENT, not merely hidden: no scheduled job of it may run (the brand-listing billing job charges money),
 * and no nav entry or page may answer. Checked against what the scheduler would actually run, not against a code comment.
 */
class LockedFeaturesAreAbsentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();   // never leave a lock list behind for the next test
        parent::tearDown();
    }

    private function lock(array $keys): void
    {
        config(['updater.product_identifier' => 'naarasim-whitelabel']);
        Setting::setValue(FeatureEntitlements::STORE_KEY, $keys, 'white_label');
        FeatureEntitlements::bust();
    }

    /** What a fresh `php artisan schedule:run` process would schedule: console.php is read again against the lock list as it is NOW. */
    private function scheduled(): string
    {
        $this->app->forgetInstance(Schedule::class);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(Schedule::class);
        require base_path('routes/console.php');

        return implode("\n", array_map(fn ($e) => (string) $e->command, $this->app->make(Schedule::class)->events()));
    }

    public function test_with_nothing_locked_every_job_is_scheduled(): void
    {
        $this->lock([]);
        $out = $this->scheduled();
        foreach (['giftcards:sync', 'giftcards:reconcile-processing', 'brand-subscriptions:bill'] as $job) {
            $this->assertStringContainsString($job, $out, $job.' should be scheduled when its feature is licensed');
        }
    }

    public function test_a_locked_feature_has_no_scheduled_jobs(): void
    {
        $this->lock(['gift_cards', 'brand_hunt']);
        $out = $this->scheduled();
        $this->assertStringNotContainsString('giftcards:sync', $out);
        $this->assertStringNotContainsString('giftcards:reconcile-processing', $out);
        $this->assertStringNotContainsString('brand-subscriptions:bill', $out, 'a locked Brand Directory must not bill subscriptions');
        $this->assertStringContainsString('cookies:prune', $out, 'unrelated jobs keep running');
    }

    /** @return array<string, list<string>> lock key => the URIs (user and admin) that belong to it */
    private function pagesByLock(): array
    {
        $g = '/'.config('admin.path');

        return [
            'gift_cards' => ['/gift-cards', '/gift-cards/orders', $g.'/gift-cards', $g.'/gift-hero'],
            'brand_hunt' => ['/brand/get-listed', '/brand/manage', '/rewards/hunt', $g.'/brand-directory', $g.'/social-hunt'],
            'preloader_customization' => [$g.'/preloader-studio'],
        ];
    }

    public function test_every_page_of_a_locked_feature_answers_404_for_members_and_admins(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create()->fresh();
        $admin->assignRole('super_admin');
        $member = \App\Models\User::factory()->create()->fresh();

        $open = [];
        foreach ($this->pagesByLock() as $lock => $uris) {
            $this->lock([$lock]);
            foreach ($uris as $uri) {
                foreach ([$admin, $member] as $who) {
                    $status = $this->actingAs($who)->get($uri)->getStatusCode();
                    if (! in_array($status, [404, 403, 302], true) && $who === $admin && str_contains($uri, config('admin.path')) || ($who === $member && ! in_array($status, [404, 403, 302], true) && ! str_contains($uri, config('admin.path')))) {
                        $open[] = "$lock: $uri as ".($who === $admin ? 'admin' : 'member')." → $status";
                    }
                }
            }
        }
        $this->assertSame([], $open, 'these pages of a LOCKED feature still answer');
    }

    public function test_a_locked_feature_has_no_admin_menu_entry_and_a_licensed_one_does(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create()->fresh();
        $admin->assignRole('super_admin');
        $dash = '/'.config('admin.path');

        $this->lock([]);
        $open = $this->actingAs($admin)->get($dash)->assertOk()->getContent();
        $this->assertStringContainsString('/gift-cards"', $open);
        $this->assertStringContainsString('/brand-directory"', $open);

        $this->lock(['gift_cards', 'brand_hunt']);
        $shut = $this->actingAs($admin)->get($dash)->assertOk()->getContent();
        $this->assertStringNotContainsString('/gift-cards"', $shut);
        $this->assertStringNotContainsString('/brand-directory"', $shut);
        $this->assertStringNotContainsString('/social-hunt"', $shut);
    }

    public function test_a_locked_page_load_shows_the_activate_sheet_but_machines_still_get_a_404(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create()->fresh();
        $admin->assignRole('super_admin');
        $member = \App\Models\User::factory()->create()->fresh();
        $this->lock(['gift_cards']);

        $this->actingAs($admin)->get('/gift-cards')->assertStatus(403)
            ->assertSee('Activate my licence')->assertSee('/'.config('admin.path').'/license', false);
        $this->actingAs($member)->get('/gift-cards')->assertStatus(403)
            ->assertSee('site owner')->assertDontSee('/license', false);
        // Not a page load: no hint, plain 404.
        $this->actingAs($member)->getJson('/gift-cards/orders')->assertStatus(404);
        $this->actingAs($member)->withHeader('X-Livewire', 'true')->get('/gift-cards')->assertStatus(404);
    }
}
