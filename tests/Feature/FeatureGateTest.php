<?php

namespace Tests\Feature;

use App\Livewire\BrandHunt;
use App\Livewire\Catalogue;
use App\Livewire\GetListed;
use App\Livewire\GiftCards;
use App\Livewire\Admin\PreloaderStudio;
use App\Models\Setting;
use App\Models\User;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 8B — the enforcement gates. Each lockable feature 404s (hidden, like any
 * disabled feature) on a white-label fork whose license locks it, and is fully
 * reachable both on that fork once unlocked AND on the master, which is never
 * gated. The gate code is universal (forks are copies of the master); only the
 * received lock list differs.
 */
class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    /** Put this deployment in "fork" mode with a given lock list. */
    private function fork(array $locks): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');
        FeatureEntitlements::store($locks);
    }

    private function user(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('user');

        return $u;
    }

    private function enableGiftCards(): void
    {
        Setting::setValue('features.naara_gift.enabled', true);
        Cache::forget('features.enabled.naara_gift');
    }

    // --- Gift cards ---

    public function test_gift_cards_are_404_on_a_fork_that_locks_them(): void
    {
        $this->enableGiftCards();
        $this->fork([FeatureLocks::F_GIFT_CARDS]);

        Livewire::actingAs($this->user())->test(GiftCards::class)->assertStatus(404);
    }

    public function test_gift_cards_are_reachable_on_a_fork_that_does_not_lock_them(): void
    {
        $this->enableGiftCards();
        $this->fork([FeatureLocks::F_BRAND_HUNT]); // something else locked, not gift cards

        Livewire::actingAs($this->user())->test(GiftCards::class)->assertStatus(200);
    }

    public function test_gift_cards_are_reachable_on_the_master(): void
    {
        $this->enableGiftCards();
        config()->set('updater.product_identifier', 'naarasim-core');
        FeatureEntitlements::bust();

        Livewire::actingAs($this->user())->test(GiftCards::class)->assertStatus(200);
    }

    // --- Preloader Studio ---

    public function test_preloader_studio_is_404_on_a_fork_that_locks_it(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->fork([FeatureLocks::F_PRELOADER]);

        Livewire::actingAs($admin)->test(PreloaderStudio::class)->assertStatus(404);
    }

    // --- Brand Hunt family ---

    public function test_brand_hunt_surfaces_are_404_on_a_fork_that_locks_them(): void
    {
        $this->fork([FeatureLocks::F_BRAND_HUNT]);
        $user = $this->user();

        Livewire::actingAs($user)->test(BrandHunt::class)->assertStatus(404);
        Livewire::actingAs($user)->test(GetListed::class)->assertStatus(404);
    }

    public function test_brand_hunt_is_reachable_on_the_master(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');
        FeatureEntitlements::bust();

        Livewire::actingAs($this->user())->test(BrandHunt::class)->assertStatus(200);
    }

    // --- Catalogue voice line ---

    public function test_the_voice_line_is_hidden_and_unreachable_on_a_fork_that_locks_it(): void
    {
        $this->fork([FeatureLocks::F_ESIM_VOICE]);

        Livewire::actingAs($this->user())
            ->withQueryParams(['tab' => 'full'])
            ->test(Catalogue::class)
            ->assertSet('voiceLocked', true)
            ->assertSet('tab', 'data')          // ?tab=full deep link forced back to data
            ->call('setTab', 'full')
            ->assertSet('tab', 'data');         // can't switch to the locked line
    }

    public function test_the_voice_line_works_normally_on_the_master(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');
        FeatureEntitlements::bust();

        Livewire::actingAs($this->user())
            ->test(Catalogue::class)
            ->assertSet('voiceLocked', false)
            ->call('setTab', 'full')
            ->assertSet('tab', 'full');
    }
}
