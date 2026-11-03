<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\FeatureEntitlements;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * WHITE LABEL EXTENDED ships with zero feature locks (blueprint §0.4): even if the master sent a lock list, nothing is locked here. The standard white label
 * has the same gating code (feature.allowed middleware, gated schedule entries, filtered admin menu) and a test that proves a locked feature is ABSENT;
 * on this edition the same code is proven to stay out of the way: every scheduled job runs and every page answers.
 */
class LockedFeaturesAreAbsentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    private function sendLocks(array $keys): void
    {
        config(['updater.product_identifier' => 'naarasim-whitelabel']);
        Setting::setValue(FeatureEntitlements::STORE_KEY, $keys, 'white_label');
        FeatureEntitlements::bust();
    }

    public function test_extended_ignores_a_lock_list_so_nothing_is_ever_locked(): void
    {
        $this->sendLocks(['gift_cards', 'brand_hunt', 'esim_voice', 'preloader_customization']);
        $this->assertSame([], FeatureEntitlements::all());
        $this->assertFalse(FeatureEntitlements::locked('gift_cards'));
        $this->assertTrue(FeatureEntitlements::allowedAtBoot('brand_hunt'));
    }

    public function test_every_job_is_scheduled_and_every_gated_page_is_open_even_when_a_lock_list_arrives(): void
    {
        $this->sendLocks(['gift_cards', 'brand_hunt', 'preloader_customization']);
        $this->app->forgetInstance(Schedule::class);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(Schedule::class);
        require base_path('routes/console.php');
        $jobs = implode("\n", array_map(fn ($e) => (string) $e->command, $this->app->make(Schedule::class)->events()));
        foreach (['giftcards:sync', 'giftcards:reconcile-processing', 'brand-subscriptions:bill'] as $job) {
            $this->assertStringContainsString($job, $jobs);
        }

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create()->fresh();
        $admin->assignRole('super_admin');
        $g = '/'.config('admin.path');
        foreach (['/gift-cards', $g.'/gift-cards', $g.'/brand-directory', $g.'/preloader-studio'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }
}
