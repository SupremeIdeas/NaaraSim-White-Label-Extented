<?php

namespace Tests\Feature;

use App\Livewire\Admin\SidebarMenu as SidebarMenuAdmin;
use App\Models\Setting;
use App\Models\User;
use App\Support\SidebarMenu;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Global glass sidebar (BUILD-3 §7) — always-present compliance links + admin CMS.
 */
class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SidebarMenu::flush();
    }

    private function activeUser(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_the_sidebar_always_offers_legal_and_account_deletion(): void
    {
        $res = $this->actingAs($this->activeUser())->get('/notifications')->assertOk();
        $res->assertSee('Open menu');            // trigger
        $res->assertSee('Legal &amp; policies', false);
        $res->assertSee('Delete my account');    // fast, prominent
    }

    public function test_admin_custom_links_and_reviews_appear_in_the_sidebar(): void
    {
        Livewire::actingAs($this->admin())->test(SidebarMenuAdmin::class)
            ->set('reviews_url', 'https://reviews.example.com')
            ->set('display_mode', 'grid')
            ->call('saveSettings')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin())->test(SidebarMenuAdmin::class)
            ->set('new_label', 'Help Center')
            ->set('new_url', 'https://help.example.com')
            ->call('addLink')
            ->assertHasNoErrors();

        $this->assertSame('grid', SidebarMenu::displayMode());
        $this->assertSame('https://reviews.example.com', SidebarMenu::reviewsUrl());

        $res = $this->actingAs($this->activeUser())->get('/notifications')->assertOk();
        $res->assertSee('Help Center');
        $res->assertSee('Rate &amp; review us', false);
    }

    public function test_a_custom_link_requires_a_valid_url(): void
    {
        Livewire::actingAs($this->admin())->test(SidebarMenuAdmin::class)
            ->set('new_label', 'Bad')
            ->set('new_url', 'not-a-url')
            ->call('addLink')
            ->assertHasErrors('new_url');

        $this->assertCount(0, SidebarMenu::customLinks());
    }

    public function test_the_blog_widget_is_off_by_default(): void
    {
        $this->assertFalse(SidebarMenu::blogWidgetEnabled());
        $this->assertTrue(SidebarMenu::blogPosts()->isEmpty());

        Setting::setValue(SidebarMenu::BLOG_KEY, true, 'sidebar');
        SidebarMenu::flush();
        $this->assertTrue(SidebarMenu::blogWidgetEnabled());
    }
}
