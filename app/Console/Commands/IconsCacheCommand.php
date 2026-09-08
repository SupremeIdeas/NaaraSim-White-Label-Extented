<?php

namespace App\Console\Commands;

use App\Support\IconOverrides;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * icons:cache (blueprint Section 16.5). Warms the custom-icon override cache
 * and pre-validates that every <x-icon name="..."> used in the views has a
 * matching sprite symbol or admin override — catching a missing icon at deploy
 * time instead of in production. Dynamic names (<x-icon :name="...">) are
 * skipped since they can't be resolved statically.
 */
class IconsCacheCommand extends Command
{
    protected $signature = 'icons:cache {--path= : views directory to scan (defaults to resources/views)}';

    protected $description = 'Warm the icon-override cache and verify every used icon has a sprite symbol';

    public function handle(): int
    {
        // Warm the override cache.
        IconOverrides::flush();
        $overrides = IconOverrides::all();

        $spriteSymbols = $this->spriteSymbols();
        $viewsPath = $this->option('path') ?: resource_path('views');
        $missing = [];

        foreach (File::allFiles($viewsPath) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $contents = $file->getContents();
            preg_match_all('/<x-icon\s+[^>]*name=["\']([\w-]+)["\']/', $contents, $matches);

            foreach ($matches[1] as $name) {
                if (! in_array($name, $spriteSymbols, true) && ! array_key_exists($name, $overrides)) {
                    $missing[$name][] = $file->getRelativePathname();
                }
            }
        }

        if ($missing !== []) {
            $this->error('Missing icons (no sprite symbol or override):');
            foreach ($missing as $name => $files) {
                $this->line("  • {$name}  — used in ".implode(', ', array_unique($files)));
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Icon cache warmed. %d sprite symbols, %d overrides — all <x-icon> references resolve.',
            count($spriteSymbols),
            count($overrides),
        ));

        return self::SUCCESS;
    }

    /** @return array<int, string> sprite symbol names without the i- prefix */
    private function spriteSymbols(): array
    {
        $sprite = File::get(resource_path('views/partials/icon-sprite.blade.php'));
        preg_match_all('/id="i-([\w-]+)"/', $sprite, $matches);

        return $matches[1];
    }
}
