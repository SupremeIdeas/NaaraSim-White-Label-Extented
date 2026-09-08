<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrandDirectory;
use App\Models\BrandPartner;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** BUILD-9 §10: admin manages plans and overrides listing status. */
class AdminBrandDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_admin_can_add_a_plan_and_override_a_listing(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $brand = BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'Acme', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#000', 'is_active' => true]);

        Livewire::actingAs($admin)->test(BrandDirectory::class)
            ->set('newPlan.name', 'Pro Reach')->set('newPlan.price_usd_per_month', 149)
            ->set('newPlan.handles_included', 8)->set('newPlan.guaranteed_followers_per_handle_per_month', 500)
            ->set('newPlan.video_previews_allowed', 2)
            ->call('addPlan')->assertHasNoErrors()
            ->call('suspendBrand', $brand->id);

        $this->assertDatabaseHas('brand_subscription_plans', ['name' => 'Pro Reach']);
        $this->assertSame(BrandPartner::STATUS_DISABLED, $brand->fresh()->listing_status);
    }

    public function test_the_admin_page_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(BrandDirectory::class)->assertStatus(403);
    }
}
