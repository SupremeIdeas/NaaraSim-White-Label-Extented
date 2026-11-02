<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The master's showcase accounts (demo brands, handles, photos) never reach a white-label install, new or upgraded. */
class NoMasterDemoContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_seeds_no_demo_brands_or_extra_accounts(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);
        $this->assertSame(0, BrandPartner::query()->count());
        $this->assertSame(1, User::query()->count(), 'only the default admin exists');
        $this->assertFileDoesNotExist(public_path('images/brand-partners'));
    }

    public function test_the_upgrade_removes_untouched_demo_brands_and_keeps_anything_a_person_owns(): void
    {
        $owner = User::factory()->create();
        foreach (['Sahara Airways', 'Lagos Eats'] as $n) {
            $id = DB::table('brand_partners')->insertGetId(['brand_name' => $n, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('brand_partner_handles')->insert(['brand_partner_id' => $id, 'platform' => 'instagram', 'handle_label' => 'demo', 'handle_url' => 'https://example.test', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('brand_partners')->insert(['brand_name' => 'Naija Fashion Co', 'owner_user_id' => $owner->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('brand_partners')->insert(['brand_name' => 'My Real Brand', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        (include database_path('migrations/2027_05_02_100000_remove_master_demo_brands.php'))->up();

        $names = DB::table('brand_partners')->pluck('brand_name')->sort()->values()->all();
        $this->assertSame(['My Real Brand', 'Naija Fashion Co'], $names);
        $this->assertSame(0, DB::table('brand_partner_handles')->count());
    }
}
