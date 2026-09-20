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
 * Batch 8B — the enforcement gates, WHITE-LABEL EXTENDED variant. Extended
 * ships with ZERO feature locks (blueprint §0.4): every lockable feature stays
 * reachable even when a lock list happens to be stored, because
 * `FeatureEntitlements::all()` is hard-empty on this build. So the "fork" cases
 * below assert reachability DESPITE a stored lock (the Extended guarantee),
 * where the normal white-label fork would 404. The master (never gated) behaves
 * the same as always.
 */
class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    /** Put this deployment in "fork" mode with a given lock list stored. On
     *  Extended the stored list is deliberately ignored by the gate. */
    private function fork(array $locks): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel-extended');
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

    public function test_gift_cards_stay_reachable_on_extended_even_when_a_lock_is_stored(): void
    {
        $this->enableGiftCards();
        $this->fork([FeatureLocks::F_GIFT_CARDS]); // Extended ignores stored locks

        Livewire::actingAs($this->user())->test(GiftCards::class)->assertStatus(200);
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

    public function test_preloader_studio_stays_reachable_on_extended_even_when_a_lock_is_stored(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->fork([FeatureLocks::F_PRELOADER]);

        Livewire::actingAs($admin)->test(PreloaderStudio::class)->assertStatus(200);
    }

    // --- Brand Hunt family ---

    public function test_brand_hunt_surfaces_stay_reachable_on_extended_even_when_a_lock_is_stored(): void
    {
        $this->fork([FeatureLocks::F_BRAND_HUNT]);
        $user = $this->user();

        Livewire::actingAs($user)->test(BrandHunt::class)->assertStatus(200);
        Livewire::actingAs($user)->test(GetListed::class)->assertStatus(200);
    }

    public function test_brand_hunt_is_reachable_on_the_master(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');
        FeatureEntitlements::bust();

        Livewire::actingAs($this->user())->test(BrandHunt::class)->assertStatus(200);
    }

    // --- Catalogue voice line ---

    public function test_the_voice_line_stays_unlocked_on_extended_even_when_a_lock_is_stored(): void
    {
        $this->fork([FeatureLocks::F_ESIM_VOICE]);

        Livewire::actingAs($this->user())
            ->test(Catalogue::class)
            ->assertSet('voiceLocked', false)
            ->call('setTab', 'full')
            ->assertSet('tab', 'full');       // Extended can always reach the voice line
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
