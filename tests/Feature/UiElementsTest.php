<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Module 32 part 1 — branded UI elements adapted from the hand-picked Uiverse
 * library (SupremeIdeas/Uicomponents), re-tokenized and self-hosted.
 */
class UiElementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_button_variants_render_with_loading_support(): void
    {
        $html = Blade::render('<x-ui.btn variant="primary" target="purchase">Pay now</x-ui.btn>');

        $this->assertStringContainsString('nx-btn--primary', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('wire:target="purchase"', $html);

        $gold = Blade::render('<x-ui.btn variant="gold" type="button">Go</x-ui.btn>');
        $this->assertStringContainsString('nx-btn--gold', $gold);
        $this->assertStringContainsString('type="button"', $gold);
    }

    public function test_switch_and_checkbox_are_real_accessible_inputs(): void
    {
        $switch = Blade::render('<x-ui.switch wire:model="csp_enabled" label="CSP" />');
        $this->assertStringContainsString('type="checkbox"', $switch);
        $this->assertStringContainsString('wire:model="csp_enabled"', $switch);
        $this->assertStringContainsString('aria-label="CSP"', $switch);

        $check = Blade::render('<x-ui.checkbox checked label="Agree" />');
        $this->assertStringContainsString('type="checkbox"', $check);
        $this->assertStringContainsString('nx-check__box', $check);
    }

    public function test_alert_tag_loader_skeleton_and_upload_render(): void
    {
        $this->assertStringContainsString('nx-alert--warning', Blade::render('<x-ui.alert variant="warning" title="T">Body</x-ui.alert>'));
        $this->assertStringContainsString('nx-tag--live', Blade::render('<x-ui.tag variant="live">Active</x-ui.tag>'));
        $this->assertStringContainsString('role="status"', Blade::render('<x-ui.loader />'));
        $this->assertStringContainsString('nx-skeleton', Blade::render('<x-ui.skeleton class="h-4" />'));

        $upload = Blade::render('<x-ui.upload label="Drop it" accept="image/png" />');
        $this->assertStringContainsString('type="file"', $upload);
        $this->assertStringContainsString('accept="image/png"', $upload);
    }

    public function test_the_toast_stack_is_mounted_globally(): void
    {
        // Any page rendered through the base layout carries the toast listener.
        $this->get('/faq')->assertOk()->assertSee('nx-toast', false);
    }

    public function test_security_page_uses_branded_switches(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->get('/adminmaster/security')
            ->assertOk()
            ->assertSee('nx-switch', false);
    }

    /**
     * Frontend-UX-fix blueprint Phase A — root-caused via Playwright: any
     * ancestor with a CSS `filter` (the sticky header applies `filter:
     * drop-shadow(...)` to its direct children via `.nx-header-fade > *`)
     * creates a new containing block for `position: fixed` descendants, so a
     * modal triggered from inside the header rendered pinned to a tiny box
     * near the trigger instead of covering the viewport. `x-teleport="body"`
     * (the same fix `global-sidebar.blade.php`'s own panel already uses for
     * the identical trap) moves the dialog out of that subtree at runtime.
     * A fitness guard: if this ever regresses, EVERY modal on the platform
     * silently breaks again the moment its trigger sits inside a filtered/
     * transformed ancestor — not just the one that was visibly reported.
     */
    public function test_the_modal_engine_teleports_out_of_any_filtered_or_transformed_ancestor(): void
    {
        $html = file_get_contents(resource_path('views/components/ui/modal.blade.php'));

        $this->assertStringContainsString('x-teleport="body"', $html);
    }

    /**
     * Frontend-UX-fix blueprint Phase C — root-caused via Playwright at 375px:
     * <x-flag-orbit>'s node positions are percentages of the hero section's
     * own height, tuned for a short/wide desktop hero. On a narrow phone the
     * same hero reflows much taller, so those percentages land the flag
     * chips directly on top of the headline/paragraph/CTA/stat numbers
     * (reproduced live — screenshot showed the chips scattered across all of
     * that text). `hidden lg:block` keeps the effect only where the hero is
     * short enough for it to have been designed for. A fitness guard: if
     * this class is ever dropped, the overlap silently comes back on every
     * phone visiting the homepage.
     */
    public function test_the_homepage_flag_orbit_is_hidden_below_the_desktop_breakpoint(): void
    {
        $html = file_get_contents(resource_path('views/marketing/home/hero.blade.php'));

        $this->assertMatchesRegularExpression(
            '/<x-flag-orbit[^>]*class="[^"]*hidden lg:block[^"]*"/',
            $html,
        );
    }

    /**
     * Frontend-UX-fix blueprint Phase C — the ONE modal engine locks
     * background scroll with `document.body.style.overflow = 'hidden'`
     * (modal.blade.php) with no scrollbar-width compensation, so on a
     * classic (non-overlay) scrollbar desktop browser every modal open/close
     * nudges the page's fixed-width sections a few pixels sideways.
     * `scrollbar-gutter: stable` reserves that gutter permanently so toggling
     * `overflow: hidden` never changes the viewport's content width.
     */
    public function test_the_page_reserves_a_stable_scrollbar_gutter_so_modals_never_shift_layout(): void
    {
        $this->assertStringContainsString(
            'scrollbar-gutter: stable',
            file_get_contents(resource_path('css/app.css')),
        );
    }

    public function test_elements_css_is_part_of_the_compiled_bundle(): void
    {
        $this->assertFileExists(resource_path('css/ui-elements.css'));
        $this->assertStringContainsString(
            "@import './ui-elements.css'",
            file_get_contents(resource_path('css/app.css')),
        );

        // The compiled bundle actually contains the element classes. Build
        // artifacts are gitignored and CI doesn't run `npm run build`, so this
        // half only runs where a build exists (local / deploy pipeline).
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->markTestSkipped('No compiled Vite build present (CI runs PHP tests only).');
        }

        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $cssFile = collect($manifest)->pluck('css')->flatten()->first()
            ?? collect($manifest)->pluck('file')->first(fn ($f) => str_ends_with((string) $f, '.css'));
        $this->assertNotNull($cssFile);
        $this->assertStringContainsString('nx-btn', file_get_contents(public_path('build/'.$cssFile)));
    }
}
