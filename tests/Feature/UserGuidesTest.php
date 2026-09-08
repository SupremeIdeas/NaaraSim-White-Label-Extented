<?php

namespace Tests\Feature;

use App\Livewire\Admin\Guides as AdminGuides;
use App\Livewire\Guide;
use App\Models\Merchant;
use App\Models\User;
use App\Support\UserGuides;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Per-audience user guides + agreement: default seeding, audience auto-detection,
 * the one-account policy content, the in-app view, and admin editing.
 */
class UserGuidesTest extends TestCase
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

    private function merchant(string $tier = Merchant::TIER_STANDARD): User
    {
        $owner = User::factory()->create(['is_active' => true]);
        Merchant::create(['owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id, 'status' => 'active', 'tier' => $tier, 'reseller_margin_pct' => 10]);

        return $owner->fresh();
    }

    public function test_guides_seed_defaults_and_carry_the_one_account_policy(): void
    {
        $guide = UserGuides::for('user');

        $this->assertNotEmpty($guide->sections);
        $this->assertStringContainsString('multiple accounts', strtolower($guide->agreement));
        $this->assertStringContainsString('one verified account', strtolower($guide->agreement));
    }

    public function test_audience_is_detected_from_the_user(): void
    {
        $this->assertSame('user', UserGuides::audienceFor(User::factory()->create()));
        $this->assertSame('merchant', UserGuides::audienceFor($this->merchant()));
        $this->assertSame('merchant_v2', UserGuides::audienceFor($this->merchant(Merchant::TIER_V2)));
    }

    public function test_in_app_guide_shows_the_right_audience(): void
    {
        Livewire::actingAs($this->merchant(Merchant::TIER_V2))->test(Guide::class)
            ->assertSet('audience', 'merchant_v2')
            ->assertSee('Merchant V2 guide');
    }

    public function test_admin_can_edit_a_guide_and_body_is_sanitized(): void
    {
        Livewire::actingAs(User::factory()->create())->test(AdminGuides::class)->assertStatus(403);

        Livewire::actingAs($this->admin())->test(AdminGuides::class)
            ->call('selectAudience', 'developer')
            ->set('title', 'Dev handbook')
            ->set('sections', [['heading' => 'Auth', 'body' => '<p>Use keys</p><script>x()</script>', 'image' => '']])
            ->call('save')->assertHasNoErrors();

        $guide = UserGuides::for('developer');
        $this->assertSame('Dev handbook', $guide->title);
        $this->assertStringContainsString('Use keys', $guide->sections[0]['body']);
        $this->assertStringNotContainsString('<script', $guide->sections[0]['body']);
    }
}
