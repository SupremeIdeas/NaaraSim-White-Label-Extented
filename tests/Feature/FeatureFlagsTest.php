<?php

namespace Tests\Feature;

use App\Livewire\Admin\Features;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\RefundNotification;
use App\Support\FeatureFlags;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature toggles (owner request). Admin can switch features on/off on top of
 * the key-driven "Coming Soon" — a feature is only LIVE when switched on AND its
 * keys are present. Web push respects the flag.
 */
class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        FeatureFlags::flush();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_a_feature_is_live_only_when_switched_on_and_configured(): void
    {
        // No VAPID keys → not configured → not live even though default-on.
        config(['webpush.vapid.public_key' => '', 'webpush.vapid.private_key' => '']);
        FeatureFlags::flush();
        $this->assertFalse(FeatureFlags::enabled('naara_push'));
        $this->assertTrue(FeatureFlags::adminEnabled('naara_push')); // switched on by default

        // Keys present → live.
        config(['webpush.vapid.public_key' => 'PUB', 'webpush.vapid.private_key' => 'PRIV']);
        FeatureFlags::flush();
        $this->assertTrue(FeatureFlags::enabled('naara_push'));
    }

    public function test_admin_can_toggle_a_feature_off_even_without_keys(): void
    {
        config(['webpush.vapid.public_key' => 'PUB', 'webpush.vapid.private_key' => 'PRIV']);
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Features::class)
            ->call('toggle', 'naara_push');

        FeatureFlags::flush();
        $this->assertFalse(FeatureFlags::adminEnabled('naara_push'));
        $this->assertFalse(FeatureFlags::enabled('naara_push')); // off wins
    }

    public function test_a_non_admin_cannot_open_features(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Features::class)->assertForbidden();
    }

    public function test_web_push_is_not_sent_when_the_feature_is_switched_off(): void
    {
        config(['webpush.vapid.public_key' => 'PUB', 'webpush.vapid.private_key' => 'PRIV']);
        Queue::fake();
        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'https://p/1', 'endpoint_hash' => hash('sha256', 'https://p/1'),
            'public_key' => 'k', 'auth_token' => 'a',
        ]);

        FeatureFlags::setEnabled('naara_push', false);
        $user->notifyNow(new RefundNotification(5.00, 'USD'));

        Queue::assertNotPushed(\App\Jobs\SendWebPushJob::class);
    }

    public function test_the_features_page_shows_the_web_push_setup_guide(): void
    {
        Livewire::actingAs($this->admin())->test(Features::class)
            ->call('showGuide', 'naara_push')
            ->assertSee('php artisan webpush:vapid')
            ->assertSee('VAPID_PUBLIC_KEY');
    }
}
