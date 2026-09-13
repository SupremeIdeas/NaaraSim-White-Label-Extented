<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteChromePage;
use App\Models\Setting;
use App\Models\User;
use App\Support\FeatureEntitlements;
use App\Support\SiteChrome;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Theme-integrity blueprint §1.4/§2 — the footer credit line used to be
 * hand-duplicated (with NO real link) across site-footer.blade.php and all
 * 12 theme-sections/footer/* variants. Now it's one shared component fed by
 * SiteChrome, so a single Setting change reaches every footer, and the
 * "Supreme Ideas Agency" name always links to supremeideas.agency.
 */
class FooterCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SiteChrome::flush();
        FeatureEntitlements::bust();
    }

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        return $admin;
    }

    // --- default phrasing per repo identity (§2.3) ---

    public function test_the_master_platform_defaults_to_product_of_phrasing(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');

        $this->assertSame(SiteChrome::CREDIT_PRODUCT_OF, SiteChrome::footerCreditPhrasing());
    }

    public function test_a_white_label_fork_defaults_to_made_with_love_phrasing(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        $this->assertSame(SiteChrome::CREDIT_MADE_WITH_LOVE, SiteChrome::footerCreditPhrasing());
    }

    // --- the link is always present, always real ---

    public function test_the_homepage_footer_links_the_agency_name(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="https://supremeideas.agency"', false)
            ->assertSee('Supreme Ideas Agency');
    }

    // --- admin can change the phrasing, and every footer reflects it ---

    public function test_admin_switches_to_made_with_love_and_it_renders_site_wide(): void
    {
        Livewire::actingAs($this->admin())->test(SiteChromePage::class)
            ->set('credit_phrasing', SiteChrome::CREDIT_MADE_WITH_LOVE)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(SiteChrome::CREDIT_MADE_WITH_LOVE, SiteChrome::footerCreditPhrasing());

        $this->get('/')
            ->assertOk()
            ->assertSee('Made with love by')
            ->assertSee('href="https://supremeideas.agency"', false);
    }

    public function test_admin_sets_custom_phrasing_with_the_agency_placeholder(): void
    {
        Livewire::actingAs($this->admin())->test(SiteChromePage::class)
            ->set('credit_phrasing', SiteChrome::CREDIT_CUSTOM)
            ->set('credit_custom_text', 'An independent build by {agency}, for travellers everywhere.')
            ->call('save')
            ->assertHasNoErrors();

        $parts = SiteChrome::footerCreditParts();
        $this->assertSame('An independent build by ', $parts['prefix']);
        $this->assertSame(', for travellers everywhere.', $parts['suffix']);
    }

    public function test_custom_phrasing_without_the_placeholder_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(SiteChromePage::class)
            ->set('credit_phrasing', SiteChrome::CREDIT_CUSTOM)
            ->set('credit_custom_text', 'A build by Supreme Ideas Agency.')
            ->call('save')
            ->assertHasErrors(['credit_custom_text']);
    }

    public function test_custom_phrasing_requires_text_at_all(): void
    {
        Livewire::actingAs($this->admin())->test(SiteChromePage::class)
            ->set('credit_phrasing', SiteChrome::CREDIT_CUSTOM)
            ->set('credit_custom_text', '')
            ->call('save')
            ->assertHasErrors(['credit_custom_text']);
    }

    // --- fail-safe: a stray/legacy setting value never breaks the footer ---

    public function test_an_unrecognized_stored_phrasing_falls_back_to_the_repo_default(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');
        Setting::setValue('site.footer.credit_phrasing', 'some-removed-value', 'site');
        SiteChrome::flush();

        $this->assertSame(SiteChrome::CREDIT_PRODUCT_OF, SiteChrome::footerCreditPhrasing());
    }

    public function test_custom_phrasing_with_a_missing_placeholder_fails_open_to_the_standard_phrasing(): void
    {
        // Simulates data saved before validation existed, or edited directly —
        // SiteChrome itself must degrade safely, not just the admin form.
        Setting::setValue('site.footer.credit_phrasing', SiteChrome::CREDIT_CUSTOM, 'site');
        Setting::setValue('site.footer.credit_custom_text', 'No placeholder here.', 'site');
        SiteChrome::flush();

        $parts = SiteChrome::footerCreditParts();
        $this->assertSame('A product of ', $parts['prefix']);
        $this->assertSame('.', $parts['suffix']);
    }
}
