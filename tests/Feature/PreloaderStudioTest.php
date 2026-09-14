<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\BrandSettings;
use App\Support\PreloaderSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\Admin\PreloaderStudio;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class PreloaderStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_unconfigured_mirrors_brandsettings_exactly(): void
    {
        // Zero-regression guarantee: with no assignment saved, forPageType()
        // returns today's behaviour (BrandSettings enabled + legacy style).
        $cfg = PreloaderSettings::forPageType('dashboard');

        $this->assertSame(BrandSettings::preloaderEnabled(), $cfg['enabled']);
        $this->assertSame(BrandSettings::preloaderStyle(), $cfg['preset']);
        $this->assertTrue(PreloaderSettings::isLegacy($cfg['preset']));
    }

    public function test_saved_assignment_resolves_and_inherit_falls_through_to_default(): void
    {
        PreloaderSettings::saveAssignment('default', array_merge(
            PreloaderSettings::safeDefault(),
            ['preset' => 'wifi-rings', 'enabled' => true],
        ));
        PreloaderSettings::saveAssignment('checkout', ['inherit' => true]);

        $this->assertSame('wifi-rings', PreloaderSettings::forPageType('default')['preset']);
        // checkout inherits default.
        $this->assertSame('wifi-rings', PreloaderSettings::forPageType('checkout')['preset']);
    }

    public function test_corrupt_preset_slug_degrades_to_safe_default(): void
    {
        PreloaderSettings::saveAssignment('default', ['preset' => 'not-a-real-preset', 'enabled' => true]);

        $cfg = PreloaderSettings::forPageType('default');
        $this->assertSame(PreloaderSettings::safeDefault()['preset'], $cfg['preset']);
    }

    public function test_feature_code_registers_its_own_page_type(): void
    {
        PreloaderSettings::registerPageType('numbers', 'Numbers & Virtual Lines');

        $types = PreloaderSettings::pageTypes();
        $this->assertArrayHasKey('numbers', $types);
        $this->assertSame('Numbers & Virtual Lines', $types['numbers']);
        // Built-ins are always present.
        $this->assertArrayHasKey('default', $types);
    }

    public function test_resolve_css_vars_emits_brand_aware_properties(): void
    {
        $cfg = array_merge(PreloaderSettings::safeDefault(), [
            'size' => 'lg', 'speed' => 2.0, 'opacity' => 0.5,
            'blur' => true, 'blur_style' => 'heavy',
            'use_brand_color' => false, 'colors' => ['#D4A017'],
        ]);
        $vars = PreloaderSettings::resolveCssVars($cfg);

        $this->assertStringContainsString('--nx-pl-scale:1.400', $vars);
        $this->assertStringContainsString('--nx-pl-speed:2.00', $vars);
        $this->assertStringContainsString('--nx-pl-opacity:0.50', $vars);
        $this->assertStringContainsString('--nx-pl-blur:20px', $vars);
        // Manual colour maps to channel triple (accent gold).
        $this->assertStringContainsString('--nx-pl-c1:212 160 23', $vars);
    }

    public function test_every_catalog_preset_partial_compiles(): void
    {
        foreach (array_keys(PreloaderSettings::PRESETS) as $slug) {
            $view = 'components.preloaders.'.$slug;
            $this->assertTrue(view()->exists($view), "Missing partial: {$slug}");
            // Render with an empty cfg — text presets must tolerate it.
            $html = view($view, ['cfg' => ['loading_text' => 'Loading']])->render();
            $this->assertIsString($html);
        }
    }

    public function test_preloader_disabled_by_default_renders_nothing(): void
    {
        $html = Blade::render('<x-brand-preloader page-type="dashboard" />');
        $this->assertStringNotContainsString('nx-preloader', $html);
    }

    public function test_configured_preset_renders_its_partial(): void
    {
        PreloaderSettings::saveAssignment('marketing', array_merge(
            PreloaderSettings::safeDefault(),
            ['preset' => 'equalizer', 'enabled' => true],
        ));

        $html = Blade::render('<x-brand-preloader page-type="marketing" />');
        $this->assertStringContainsString('nx-pl--equalizer', $html);
        $this->assertStringContainsString('id="nx-preloader"', $html);
    }

    /**
     * Regression (2026-09-08): the preloader only listened for the browser
     * `load` event, which never fires again on a Livewire `wire:navigate`
     * transition (a client-side morph of an already-loaded page) — so every
     * in-app navigation sat idle for the full 4s hard-fallback before
     * self-removing, a multi-second hang on every single page. Fixed by
     * finishing immediately when `document.readyState` is already
     * `'complete'` at the moment the script runs (true on every wire:navigate
     * swap, never true on the real first load) — assert the guard is present
     * in the rendered script so a future edit can't silently drop it again.
     */
    public function test_the_script_finishes_immediately_when_the_document_is_already_complete(): void
    {
        PreloaderSettings::saveAssignment('marketing', array_merge(
            PreloaderSettings::safeDefault(),
            ['preset' => 'equalizer', 'enabled' => true],
        ));

        $html = Blade::render('<x-brand-preloader page-type="marketing" />');

        $this->assertStringContainsString("document.readyState === 'complete'", $html);
        $this->assertStringContainsString('window.addEventListener(\'load\', finish)', $html);
    }

    public function test_admin_preview_query_param_forces_a_preset_only_for_privileged_users(): void
    {
        // Guest: preview param is ignored (nothing renders when disabled).
        request()->merge(['preloader_preview' => 'crystals']);
        $this->assertStringNotContainsString('nx-pl--crystals', Blade::render('<x-brand-preloader page-type="dashboard" />'));

        // Admin: preview param forces the preset on.
        $this->actingAs($this->admin());
        request()->merge(['preloader_preview' => 'crystals']);
        $html = Blade::render('<x-brand-preloader page-type="dashboard" />');
        $this->assertStringContainsString('nx-pl--crystals', $html);
    }

    public function test_studio_page_is_admin_only(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(PreloaderStudio::class)->assertForbidden();
    }

    public function test_admin_can_assign_and_tune_a_preset(): void
    {
        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->call('loadType', 'marketing')
            ->call('selectPreset', 'wifi-rings')
            ->set('enabled', true)
            ->set('speed', 1.5)
            ->set('loadingText', 'Almost there')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', 'marketing');

        $cfg = PreloaderSettings::forPageType('marketing');
        $this->assertSame('wifi-rings', $cfg['preset']);
        $this->assertSame(1.5, $cfg['speed']);
        $this->assertSame('Almost there', $cfg['loading_text']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'preloader.assignment_updated']);
    }

    public function test_non_default_type_can_inherit_from_default(): void
    {
        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->call('loadType', 'checkout')
            ->set('inherit', true)
            ->call('save')
            ->assertHasNoErrors();

        // Default resolves to the legacy safe default; checkout inherits it.
        $this->assertSame(
            PreloaderSettings::forPageType('default')['preset'],
            PreloaderSettings::forPageType('checkout')['preset'],
        );
    }

    public function test_loading_text_over_limit_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->call('selectPreset', 'progress-text')
            ->set('loadingText', str_repeat('x', 40))
            ->call('save')
            ->assertHasErrors('loadingText');
    }

    /**
     * Premium (Supreme Ideas curated "favorite") presets are the original
     * platform's alone — docs/ui-component-library/premium-preloaders-MASTER-ONLY.md.
     * A white-label fork is a byte-for-byte copy of this same code, so the
     * boundary must hold even against a direct component call that skips the
     * picker UI where the preset is already hidden.
     */
    public function test_master_platform_can_select_and_save_a_premium_preset(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');

        $this->assertArrayHasKey('neon-rings', PreloaderSettings::availablePresets());

        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->call('selectPreset', 'neon-rings')
            ->assertSet('preset', 'neon-rings')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('neon-rings', PreloaderSettings::forPageType('default')['preset']);
    }

    public function test_a_white_label_fork_cannot_select_or_save_a_premium_preset(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        $this->assertArrayNotHasKey('neon-rings', PreloaderSettings::availablePresets());

        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->call('selectPreset', 'neon-rings')
            ->assertSet('preset', 'simple-pulse'); // unchanged — the click was ignored

        // Even a direct call bypassing the picker's own hidden state is refused.
        Livewire::actingAs($this->admin())->test(PreloaderStudio::class)
            ->set('preset', 'neon-rings')
            ->call('save')
            ->assertForbidden();
    }

    /**
     * Owner spot-check (2026-09-14): several presets declared more or fewer
     * "Colour N" pickers than their compiled CSS actually reads, so editing a
     * colour in the Studio silently did nothing (or a colour the CSS reads
     * was never exposed to edit at all) — exactly the kind of "changes but
     * nothing visibly happens" bug that reads as the preset having no real
     * meaning. Locks every preset's declared `c` count to the highest
     * --nx-pl-cN its own CSS block actually references, with no gaps, so a
     * future preset edit can't silently reintroduce a dead or missing slot.
     */
    public function test_every_presets_declared_colour_count_matches_its_compiled_css(): void
    {
        $css = file_get_contents(resource_path('css/preloaders.css'));

        preg_match_all('/\/\* (\d+) — ([^*]+)\*\//', $css, $markers, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($markers[0], 'preloaders.css numbered preset markers not found');

        $order = [
            'equalizer', 'wifi-rings', 'corner-beams', 'crystals', 'svg-rings', 'cube-pulse',
            'dot-blast', 'dot-orbit-3d', 'simple-pulse', 'heart-square', 'dot-grid', 'sphere-wave',
            'progress-text', 'worm-ring', 'letters', 'spokes', 'goo-dots', 'neon-rings',
            'fintech-breath', 'flying-files',
        ];
        $this->assertSame($order, array_keys(PreloaderSettings::PRESETS), 'preset order/catalog drifted — update this test\'s $order to match');

        $offsets = array_column($markers[0], 1);
        foreach ($order as $i => $slug) {
            $start = $offsets[$i] + strlen($markers[0][$i][0]);
            $end = $offsets[$i + 1] ?? strlen($css);
            $block = substr($css, $start, $end - $start);

            preg_match_all('/nx-pl-c(\d)/', $block, $used);
            $usedIndices = array_unique(array_map('intval', $used[1]));
            sort($usedIndices);

            $declared = PreloaderSettings::PRESETS[$slug]['c'];
            $expected = $declared > 0 ? range(1, $declared) : [];

            $this->assertSame(
                $expected, $usedIndices,
                "{$slug}: declared c={$declared} but its CSS block uses --nx-pl-c indices [".implode(',', $usedIndices)."]"
            );
        }
    }
}
