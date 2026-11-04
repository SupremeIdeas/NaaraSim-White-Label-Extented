<?php

namespace Tests\Feature;

use App\Livewire\Admin\ActivationBranding;
use App\Models\Setting;
use App\Models\User;
use App\Support\BrandSettings;
use App\Support\BrandWordFilter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A white label must never greet its customers with the vendor's name. Until the licensee activates and brands it, it wears a neutral name;
 * the moment they save their own, every page, line of copy and email says that instead, without touching scripts, classes or links.
 */
class BrandActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        BrandSettings::flush();
    }

    private function visibleText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b.*?</\1>#is', '', $html);

        return html_entity_decode(strip_tags($html));
    }

    public function test_a_fresh_install_says_nothing_of_the_vendor(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<title>'.BrandSettings::SHIPPED_NAME, $html);
        $text = $this->visibleText($html);
        $this->assertStringNotContainsString('Naara', $text);
        $this->assertStringNotContainsString('Supreme Ideas', $text);
    }

    public function test_pages_of_a_fresh_install_are_clean_too(): void
    {
        foreach (['/about', '/how-it-works', '/contact', '/login', '/register', '/pricing'] as $path) {
            $r = $this->get($path);
            if ($r->status() !== 200) {
                continue;
            }
            $text = $this->visibleText($r->getContent());
            $this->assertStringNotContainsString('Naara', $text, "{$path} still shows the vendor name");
            $this->assertStringNotContainsString('Supreme Ideas', $text, "{$path} still shows the vendor credit");
        }
    }

    public function test_the_licensees_brand_replaces_the_name_everywhere(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ActivationBranding::class)
            ->set('brand_name', 'Acme Mobile')->set('brand_word', 'Acme')->set('legal_name', 'Acme Mobile Ltd')->set('agency_name', 'Acme Mobile Ltd')
            ->set('support_email', 'help@acme.test')->call('save')->assertHasNoErrors();

        BrandSettings::flush();
        $this->assertSame('Acme Mobile', BrandSettings::name());
        $this->assertSame('Acme', BrandSettings::word());
        $this->assertSame('Acme Data', BrandSettings::rebrand('Naara Data'));
        $this->assertSame('Acme Mobile', BrandSettings::rebrand('NaaraSim'));

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<title>Acme Mobile', $html);
        $this->assertStringNotContainsString('Naara', $this->visibleText($html));
    }

    public function test_the_page_validates_and_is_for_admins_only(): void
    {
        Livewire::actingAs(User::factory()->create(['is_active' => true]))->test(ActivationBranding::class)->assertForbidden();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(ActivationBranding::class)
            ->set('brand_name', 'A')->set('brand_word', '<b>')->set('support_email', 'nope')->set('website', 'javascript:alert(1)')
            ->call('save')->assertHasErrors(['brand_name', 'brand_word', 'support_email', 'website']);
        $this->assertNull(Setting::getValue('brand.name'));
    }

    public function test_the_filter_only_touches_text_and_human_attributes(): void
    {
        Setting::setValue('brand.name', 'Acme Mobile');
        Setting::setValue('brand.word', 'Acme');
        BrandSettings::flush();

        $in = '<a href="/naara/NaaraSim" class="NaaraNav" title="Naara Rent" id="Naara">Welcome to Naara</a><script>window.NaaraRing = "Naara";</script><style>.Naara{}</style>';
        $out = BrandWordFilter::html($in);

        $this->assertStringContainsString('title="Acme Rent"', $out);
        $this->assertStringContainsString('>Welcome to Acme</a>', $out);
        $this->assertStringContainsString('href="/naara/NaaraSim"', $out);
        $this->assertStringContainsString('class="NaaraNav"', $out);
        $this->assertStringContainsString('id="Naara"', $out);
        $this->assertStringContainsString('window.NaaraRing = "Naara";', $out);
        $this->assertStringContainsString('.Naara{}', $out);
    }

    public function test_no_vendor_logo_files_ship(): void
    {
        $this->assertSame([], BrandSettings::LOGO_DEFAULTS);
        $this->assertSame([], glob(public_path('brand/*')) ?: []);
        $this->get('/brand-mark.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }
}
