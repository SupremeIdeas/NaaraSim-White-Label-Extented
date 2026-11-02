<?php

namespace Tests\Feature;

use Database\Seeders\DefaultAdminSeeder;
use Tests\TestCase;

/**
 * This white-label install has ONE default admin login: /adminmaster with the white-label pair below. The master platform's own default admin must
 * never appear anywhere in this repository (owner decision, 2026-11-02), and the admin path stays editable (Admin > Security), never hard-coded in routes.
 */
class WhiteLabelDefaultAdminTest extends TestCase
{
    public function test_the_default_admin_pair_is_the_white_label_pair(): void
    {
        $this->assertSame('adminmaster1234@gmail.com', DefaultAdminSeeder::EMAIL);
        $this->assertSame('123456789@AdminMaster', DefaultAdminSeeder::PASSWORD);
        $this->assertSame('adminmaster', config('admin.path'));
    }

    public function test_the_master_platforms_default_admin_appears_nowhere_in_this_repository(): void
    {
        $needles = ['supremeideasz'.'@gmail.com', '22504108303'.'@AdminMaster'];
        $root = base_path();
        $hits = [];
        foreach (['app', 'config', 'database', 'resources', 'routes', 'tests', 'docs', 'PROGRESS.md', 'README.md', 'CLAUDE.md'] as $path) {
            $full = $root.'/'.$path;
            if (! file_exists($full)) {
                continue;
            }
            $files = is_dir($full) ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS)) : [new \SplFileInfo($full)];
            foreach ($files as $f) {
                if (! $f->isFile() || $f->getSize() > 3_000_000 || $f->getRealPath() === __FILE__) {
                    continue;
                }
                $text = @file_get_contents($f->getRealPath());
                foreach ($needles as $n) {
                    if ($text !== false && str_contains($text, $n)) {
                        $hits[] = str_replace($root.'/', '', $f->getRealPath());
                    }
                }
            }
        }
        $this->assertSame([], array_values(array_unique($hits)), 'the master default admin must not appear in a white-label repo');
    }

    public function test_the_admin_routes_use_the_editable_gateway_not_a_baked_in_path(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringNotContainsString("prefix(config('admin.path'))", $routes);
        $this->assertStringContainsString("prefix('{adminGateway}')", $routes);
    }
}
