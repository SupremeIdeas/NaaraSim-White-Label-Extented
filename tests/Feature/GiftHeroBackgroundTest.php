<?php

namespace Tests\Feature;

use App\Livewire\Admin\GiftHero;
use App\Livewire\GiftCards;
use App\Models\Setting;
use App\Models\User;
use App\Support\GiftHeroBackground;
use App\Support\HeroBackground;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Naara Gift storefront hero (owner request): the SAME visual system as the
 * customer dashboard home hero, under its own giftcard.hero.* namespace so it
 * can be re-themed independently. An unconfigured install shows the text-only
 * default ("Naara Gift" + the shipped description) with no injected image.
 */
class GiftHeroBackgroundTest extends TestCase
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

    private function enableGiftFeature(): void
    {
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        Cache::forget('features.enabled.naara_gift');
    }

    public function test_unconfigured_install_shows_the_default_title_and_description(): void
    {
        $this->assertSame(GiftHeroBackground::DEFAULT_TITLE, GiftHeroBackground::title());
        $this->assertSame('Naara', GiftHeroBackground::titleFirstWord());
        $this->assertSame('Gift', GiftHeroBackground::titleRestWords());
        $this->assertSame(GiftHeroBackground::DEFAULT_DESCRIPTION, GiftHeroBackground::description());
        $this->assertFalse(GiftHeroBackground::isSet());
        $this->assertFalse(GiftHeroBackground::showsImage());

        $this->enableGiftFeature();
        Livewire::actingAs(User::factory()->create())->test(GiftCards::class)
            ->assertSee('Naara')
            ->assertSee('Gift')
            ->assertSee(GiftHeroBackground::DEFAULT_DESCRIPTION);
    }

    public function test_admin_page_is_gated_and_renders(): void
    {
        Livewire::actingAs(User::factory()->create())->test(GiftHero::class)->assertStatus(403);

        Livewire::actingAs($this->admin())->test(GiftHero::class)
            ->assertOk()
            ->assertSee('Naara Gift hero');
    }

    public function test_admin_can_override_title_description_and_size(): void
    {
        Livewire::actingAs($this->admin())->test(GiftHero::class)
            ->set('title', '  Send Joy  ')
            ->set('description', '  Instant delivery, every brand.  ')
            ->set('title_size', 'xl')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Send Joy', GiftHeroBackground::title());
        $this->assertSame('Instant delivery, every brand.', GiftHeroBackground::description());
        $this->assertSame('xl', GiftHeroBackground::titleSize());

        $this->enableGiftFeature();
        Livewire::actingAs(User::factory()->create())->test(GiftCards::class)
            ->assertSee('Send')
            ->assertSee('Joy')
            ->assertSee('Instant delivery, every brand.')
            ->assertSee('text-[3rem]', false);
    }

    public function test_blank_title_falls_back_to_the_default(): void
    {
        Setting::setValue(GiftHeroBackground::TITLE_KEY, 'Custom', 'giftcards');
        GiftHeroBackground::flush();

        Livewire::actingAs($this->admin())->test(GiftHero::class)
            ->set('title', '   ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(GiftHeroBackground::DEFAULT_TITLE, GiftHeroBackground::title());
    }

    public function test_admin_uploads_and_removes_hero_images(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);

        Livewire::actingAs($this->admin())->test(GiftHero::class)
            ->set('hero_light', UploadedFile::fake()->image('gift-light.jpg', 1600, 800))
            ->set('hero_dark', UploadedFile::fake()->image('gift-dark.jpg', 1600, 800))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(GiftHeroBackground::isSet());
        $this->assertNotNull(GiftHeroBackground::light());
        $this->assertNotNull(GiftHeroBackground::dark());

        Livewire::actingAs($this->admin())->test(GiftHero::class)->call('removeImages');

        $this->assertFalse(GiftHeroBackground::isSet());
    }

    public function test_admin_can_toggle_the_image_off_without_deleting_it(): void
    {
        Setting::setValue(GiftHeroBackground::LIGHT_KEY, 'https://cdn/gift.webp', 'giftcards');
        GiftHeroBackground::flush();
        $this->assertTrue(GiftHeroBackground::showsImage());

        Livewire::actingAs($this->admin())->test(GiftHero::class)
            ->set('enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(GiftHeroBackground::enabled());
        $this->assertFalse(GiftHeroBackground::showsImage());
        $this->assertTrue(GiftHeroBackground::isSet()); // still uploaded

        $this->enableGiftFeature();
        Livewire::actingAs(User::factory()->create())->test(GiftCards::class)
            ->assertDontSee('https://cdn/gift.webp', false);
    }

    public function test_the_hero_is_independent_of_the_dashboard_hero(): void
    {
        Setting::setValue(HeroBackground::TITLE_KEY, 'My Connectivity Custom', 'brand');
        HeroBackground::flush();

        // Setting the dashboard hero title never touches the gift hero.
        $this->assertSame(GiftHeroBackground::DEFAULT_TITLE, GiftHeroBackground::title());
    }
}
