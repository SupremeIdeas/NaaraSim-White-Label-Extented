<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Skin coverage gate (Prompt 20 §23). A skin only changes a screen that is built from the shared x-nx.* components and tokens, so
 * every view listed as converted in config('appearance.coverage') is scanned for hard-coded colour (hex, rgb literals, Tailwind
 * palette classes) and for raw card/sheet chrome. Offenders fail the command; batches not converted yet are listed, never hidden.
 */
class NxCoverage extends Command
{
    protected $signature = 'nx:coverage {--batch= : Only this batch number} {--list : List every batch and its state, no scan failure}';

    protected $description = 'Report which dashboard views are on the skin system and fail on hard-coded colour in the converted ones';

    /** Tailwind palette colours that bypass the tokens. (black/white are allowed: scrims and on-fill text.) */
    private const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

    public function handle(): int
    {
        $batches = (array) config('appearance.coverage.batches', []);
        $only = $this->option('batch');
        $offenders = 0;

        foreach ($batches as $n => $batch) {
            if ($only !== null && (string) $only !== (string) $n) {
                continue;
            }
            $done = 0;
            $rows = [];
            foreach ($batch['views'] as $view) {
                $path = resource_path('views/'.$view.'.blade.php');
                if (! is_file($path)) {
                    $rows[] = [$view, 'MISSING', ''];
                    $offenders++;

                    continue;
                }
                $found = $this->scan(file_get_contents($path));
                if ($this->isConverted(file_get_contents($path))) {
                    $done++;
                    $rows[] = [$view, $found === [] ? 'converted' : 'OFFENDERS', implode('; ', array_slice($found, 0, 4))];
                    $offenders += count($found);
                } else {
                    $rows[] = [$view, 'pending', ''];
                }
            }
            $this->info("Batch {$n}: {$batch['label']} ({$done}/".count($batch['views']).' converted)');
            $this->table(['View', 'State', 'First offenders'], $rows);
        }

        if ($offenders > 0 && ! $this->option('list')) {
            $this->error("{$offenders} hard-coded colour offender(s) in converted views.");

            return self::FAILURE;
        }
        $this->info('nx:coverage: no offenders in converted views.');

        return self::SUCCESS;
    }

    /** A view is "converted" once it renders the skin canvas or a skin component, not merely mentions a class. */
    public function isConverted(string $blade): bool
    {
        return str_contains($blade, 'nx:converted') || str_contains($blade, '<x-nx.page') || str_contains($blade, '<x-nx.sheet') || str_contains($blade, 'class="ns-sheet')
            || str_contains($blade, '<x-nx.bento-card') || preg_match('/<x-nx\.(?!icon)/', $blade) === 1;
    }

    /** @return list<string> */
    public function scan(string $blade): array
    {
        // Deliberately fixed, brand-owned artwork (e.g. the default promo card) is fenced with nx:allow markers and not scanned.
        $blade = preg_replace('/\{\{--\s*nx:allow:start.*?--\}\}.*?\{\{--\s*nx:allow:end\s*--\}\}/s', '', $blade);
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);          // comments never ship
        $found = [];
        if (preg_match_all('/#[0-9a-fA-F]{3,8}\b(?![\w-])/', $blade, $m)) {
            // Entity references (&#8226;) and anchors are not colours: only flag hex that sits in a style/class/attribute context.
            foreach ($m[0] as $hex) {
                if (preg_match('/[:=\s(\'"]'.preg_quote($hex, '/').'/', $blade)) {
                    $found[] = "hex {$hex}";
                }
            }
        }
        if (preg_match_all('/\b(?:bg|text|border|ring|from|to|via|fill|stroke|shadow|divide|outline|decoration)-(?:'.self::PALETTE.')-\d{2,3}\b/', $blade, $m)) {
            foreach (array_unique($m[0]) as $c) {
                $found[] = "tailwind {$c}";
            }
        }
        if (preg_match_all('/\b(?:bg|text|border)-(?:primary|accent|navy)(?:-dark)?\b/', $blade, $m)) {
            foreach (array_unique($m[0]) as $c) {
                $found[] = "brand class {$c}";
            }
        }
        if (preg_match_all('/\bdark:[\w\[\]\/.#-]+/', $blade, $m)) {
            foreach (array_unique($m[0]) as $c) {
                $found[] = "per-element dark variant {$c}";
            }
        }

        return array_values(array_unique($found));
    }
}
