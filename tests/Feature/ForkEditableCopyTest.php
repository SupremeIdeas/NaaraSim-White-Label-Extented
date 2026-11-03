<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteEditor;
use App\Models\Setting;
use App\Models\User;
use App\Support\BlogSettings;
use App\Support\SiteContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** "Editable end to end": whatever the licensee changes in their admin shows on the public page, for every page that has copy. */
class ForkEditableCopyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> page => [section, field, public path, where it must appear] */
    public static function pages(): array
    {
        return [
            'home hero' => ['home', 'hero', 'headline', '/'],
            'about hero' => ['about', 'hero', 'headline', '/about'],
            'how it works hero' => ['how-it-works', 'hero', 'headline', '/how-it-works'],
            'contact hero' => ['contact', 'hero', 'headline', '/contact'],
            'pricing hero' => ['pricing', 'hero', 'headline', '/pricing'],
            'pricing estimator' => ['pricing', 'estimator', 'cta', '/pricing'],
            'login' => ['sign-in', 'login', 'heading', '/login'],
            'register' => ['sign-in', 'register', 'subheading', '/register'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("pageProvider")]
    public function test_a_change_in_the_site_editor_shows_on_the_public_page(string $page, string $section, string $field, string $path): void
    {
        $marker = 'EDITED-'.strtoupper(str_replace(' ', '', $page.$section.$field));
        Livewire::actingAs($this->admin())->test(SiteEditor::class)->set('page', $page)->set("sections.$section.$field", $marker)->call('save');
        SiteContent::flush();
        \Illuminate\Support\Facades\Auth::logout();   // the public page, seen by a visitor
        $this->get($path)->assertOk()->assertSee($marker);
    }

    public static function pageProvider(): array
    {
        return self::pages();
    }

    public function test_the_blog_heading_follows_the_blog_settings(): void
    {
        Setting::setValue(BlogSettings::KEY, ['title' => 'Field notes from the road', 'subtitle' => 'Short and honest.'], 'blog');
        $this->get('/blog')->assertOk()->assertSee('Field notes from the road')->assertSee('Short and honest.');
    }

    public function test_every_editable_page_is_listed_in_the_editor_and_the_faq_is_not(): void
    {
        $pages = SiteContent::editablePages();
        foreach (['home', 'about', 'how-it-works', 'pricing', 'contact', 'sign-in'] as $p) {
            $this->assertContains($p, $pages);
        }
        $this->assertNotContains('faq', $pages);
    }
}
