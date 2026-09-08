<?php

namespace Tests\Feature;

use App\Livewire\Admin\EsimHero;
use App\Models\Setting;
use App\Models\User;
use App\Support\EsimHeroContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * eSIM storefront hero (esim_upgrade Part 2). Admin-managed title/description +
 * up to four ordered images, with shipped defaults as a fallback.
 */
class EsimHeroTest extends TestCase
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

        return $u->fresh();
    }

    public function test_it_falls_back_to_shipped_images_when_none_configured(): void
    {
        EsimHeroContent::flush();
        $this->assertCount(4, EsimHeroContent::images());
        $this->assertStringContainsString('/img/esim/hero-1.webp', EsimHeroContent::images()[0]);
    }

    public function test_admin_can_set_title_description_and_upload_images(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);

        Livewire::actingAs($this->admin())->test(EsimHero::class)
            ->set('title', 'Roam the world')
            ->set('description', 'eSIM data everywhere.')
            ->set('images', []) // clear defaults so only the upload remains
            ->set('slot0', UploadedFile::fake()->image('slide.jpg', 1280, 540))
            ->call('save')
            ->assertHasNoErrors();

        EsimHeroContent::flush();
        $this->assertSame('Roam the world', EsimHeroContent::title());
        $this->assertCount(1, EsimHeroContent::images());
    }

    public function test_a_non_admin_cannot_open_the_editor(): void
    {
        Livewire::actingAs(User::factory()->create())->test(EsimHero::class)->assertForbidden();
    }

    public function test_the_catalogue_renders_the_hero(): void
    {
        Setting::setValue(EsimHeroContent::TITLE_KEY, 'Hero Headline Here', 'esim');
        EsimHeroContent::flush();

        Livewire::actingAs(User::factory()->create())->test(\App\Livewire\Catalogue::class)
            ->assertSee('Hero Headline Here')
            ->assertSee('nx-imghero', false);
    }
}
