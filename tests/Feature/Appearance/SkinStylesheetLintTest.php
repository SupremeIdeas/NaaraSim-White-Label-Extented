<?php

namespace Tests\Feature\Appearance;

use PHPUnit\Framework\TestCase;

/**
 * Static lint over the skin stylesheets (Prompt 20 §42): every shipped skin has its file, nothing leaks past the skin's own
 * scope, and the visual contract's hard rules (no native @scope, no emoji, Vault foil is not money-gold) hold.
 */
class SkinStylesheetLintTest extends TestCase
{
    private function config(): array
    {
        return require dirname(__DIR__, 3).'/config/appearance.php';
    }

    private function dir(): string
    {
        return dirname(__DIR__, 3).'/resources/css/nx-skins';
    }

    public function test_every_built_skin_has_a_stylesheet_that_the_index_imports(): void
    {
        $index = file_get_contents(dirname(__DIR__, 3).'/resources/css/nx-skins.css');
        foreach ($this->config()['built'] as $skin) {
            $file = $this->dir()."/{$skin}.css";
            $this->assertFileExists($file, "built skin {$skin} has no stylesheet");
            $this->assertGreaterThan(400, strlen(file_get_contents($file)), "{$skin}.css is suspiciously small");
            $this->assertStringContainsString("./nx-skins/{$skin}.css", $index);
        }
        $this->assertStringContainsString('_contrast-fixes.css', $index);
    }

    public function test_every_listed_skin_is_either_built_or_deliberately_not(): void
    {
        $cfg = $this->config();
        $this->assertSame([], array_values(array_diff($cfg['built'], array_keys($cfg['skins']))), 'a built skin is missing from the catalogue');
        $this->assertSame(35, count($cfg['skins']));
    }

    public function test_no_selector_escapes_its_own_skin(): void
    {
        foreach (glob($this->dir().'/*.css') as $file) {
            $name = basename($file, '.css');
            if ($name === '_contrast-fixes') {
                continue;
            }
            $css = file_get_contents($file);
            $this->assertStringNotContainsString('@scope', $css, "{$name}: native @scope is not used (older WebViews drop the whole rule)");
            $this->assertStringNotContainsString('data-skin=', $css, "{$name}: a wireframe attribute survived the port");
            $this->assertStringNotContainsString('url(http', $css, "{$name}: a skin must not hotlink an asset");
            $this->assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $css, "{$name}: emoji are not allowed in skins");

            // Every selector (outside @keyframes/@media wrappers) must be tied to this skin, never a bare class.
            preg_match_all('/(?:^|\})\s*([^{}@]+)\{/m', $css, $m);
            foreach ($m[1] as $selectors) {
                foreach (preg_split('/,\s*\n?/', trim($selectors)) as $sel) {
                    $sel = trim($sel);
                    if ($sel === '' || preg_match('/^\d+%|^from$|^to$/', $sel)) {
                        continue;
                    }
                    $this->assertMatchesRegularExpression("/data-nx-(skin|preview)={$name}\b/", $sel, "{$name}: selector not scoped to the skin: {$sel}");
                }
            }
        }
    }

    public function test_vault_foil_is_never_money_gold(): void
    {
        // Money gold may appear only on rules about a gold (money) element: the Rent/Buy CTA, the Popular pill. Everything else
        // in Vault is decoration and must use the foil token (§46).
        $css = file_get_contents($this->dir().'/vault.css');
        preg_match_all('/(?:^|\})\s*([^{}@]+)\{([^{}]*)\}/m', $css, $rules, PREG_SET_ORDER);
        $this->assertNotEmpty($rules);
        foreach ($rules as [, $selector, $body]) {
            if (str_contains($body, 'var(--nx-gold') && ! preg_match('/gold|pop|auto/', $selector)) {
                $this->fail('Vault decoration uses money-gold, not --nx-foil: '.trim($selector));
            }
        }
        $this->assertStringContainsString('--nx-foil', $css);
    }
    public function test_a_skin_owns_the_whole_page_not_just_the_panel(): void
    {
        // Owner direction (S3): under a skin the platform's dashboard wallpaper (brand-colour bloom) is off and the page itself
        // carries the skin's canvas, so nothing shows around or below the content panel.
        $layout = file_get_contents(resource_path('css/nx-layout.css'));
        $this->assertMatchesRegularExpression('/html\[data-nx-skin\] body\.dashboard-bg::before\s*\{[^}]*display:\s*none/', $layout);
        $this->assertMatchesRegularExpression('/html\[data-nx-skin\] \.ns-app\.ns-app--flush[^{]*\{\s*background:\s*none/', $layout);

        // Every skin that repaints the app surface mirrors that background onto the page, pinned to the viewport.
        $glass = file_get_contents($this->dir().'/glass.css');
        $this->assertStringContainsString('html[data-nx-skin=glass] body[class]{background:', $glass);
        $this->assertStringContainsString('background-attachment:fixed', $glass);
    }

    /** Owner direction 2026-10-03: the More menu is never frosted in the Glass skin, and nothing floats above the open sheet. */
    public function test_glass_more_sheet_is_solid_and_sits_above_the_floating_widgets(): void
    {
        $fixes = file_get_contents($this->dir().'/_contrast-fixes.css');
        $this->assertMatchesRegularExpression('/html\[data-nx-skin=glass\] \.ns-more\.ns-sheet\s*\{[^}]*backdrop-filter:\s*none/s', $fixes);
        $this->assertMatchesRegularExpression('/html\[data-nx-skin=glass\] \.ns-more \.ns-list\.ns-list--grid \.ns-list__row\s*\{[^}]*background:\s*rgb\(var\(--nx-surface-3\)\)/s', $fixes);

        $sheet = file_get_contents(dirname(__DIR__, 3).'/resources/views/components/more-sheet.blade.php');
        $this->assertStringContainsString('fixed inset-0 z-[60]', $sheet, 'the More sheet must stack above the z-50 floating widgets');
    }
}
