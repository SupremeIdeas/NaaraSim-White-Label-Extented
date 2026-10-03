<?php

namespace Tests\Feature\Appearance;

use PHPUnit\Framework\TestCase;

/**
 * The standing rule (owner, 2026-10-03): every member-facing page, now and in the future, is skin-aware. This guard makes that
 * mechanical: a Livewire page that renders inside the customer layout must be registered in config/appearance.php `coverage.batches`
 * (converted, or queued in a named batch). A new page that is not registered fails here, so it cannot ship un-skinned unnoticed.
 */
class SkinContractGuardTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_every_customer_layout_page_is_registered_in_the_coverage_manifest(): void
    {
        $config = require $this->root().'/config/appearance.php';
        $registered = [];
        foreach ($config['coverage']['batches'] as $batch) {
            foreach ($batch['views'] as $v) {
                $registered[$v] = true;
            }
        }

        $missing = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root().'/app/Livewire', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (! str_contains($src, 'components.layouts.customer')) {
                continue;
            }
            if (preg_match("/view\\('([a-z0-9_.\\-]+)'/", $src, $m)) {
                $view = str_replace('.', '/', $m[1]);
                if (! isset($registered[$view])) {
                    $missing[] = $view.'  ('.basename($file->getPathname()).')';
                }
            }
        }

        $this->assertSame([], $missing, "Register these pages in config/appearance.php coverage.batches (and build them from x-nx.* + tokens):\n".implode("\n", $missing));
    }

    public function test_the_help_center_layout_carries_the_members_skin_but_keeps_its_own_background(): void
    {
        $path = $this->root().'/resources/views/components/layouts/help-center.blade.php';
        if (! is_file($path)) {
            $this->markTestSkipped('This build has no Help Center layout (master-only surface); nothing to guard.');
        }
        $layout = file_get_contents($path);
        $this->assertStringContainsString(':skin="auth()->check()"', $layout);
        $this->assertStringContainsString('bg-[#F4FAFA]', $layout, 'Nia keeps its own page background (owner exception)');
    }
}
