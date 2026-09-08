<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IconsCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_for_the_real_views(): void
    {
        // Every <x-icon> in the shipped views must resolve to a sprite symbol.
        $this->artisan('icons:cache')->assertSuccessful();
    }

    public function test_fails_when_a_view_references_a_missing_icon(): void
    {
        $dir = storage_path('framework/testing/icon-views');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/broken.blade.php', '<div><x-icon name="totally-made-up" /></div>');

        try {
            $this->artisan('icons:cache', ['--path' => $dir])
                ->expectsOutputToContain('totally-made-up')
                ->assertFailed();
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_passes_when_a_temp_view_uses_a_real_icon(): void
    {
        $dir = storage_path('framework/testing/icon-views-ok');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/ok.blade.php', '<div><x-icon name="wallet" /><x-icon name="globe" /></div>');

        try {
            $this->artisan('icons:cache', ['--path' => $dir])->assertSuccessful();
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
