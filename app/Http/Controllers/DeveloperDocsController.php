<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public Developer API documentation page (ROADMAP §Layer 2). Renders the
 * canonical reference (docs/DEVELOPER-API.md) so there is ONE source of truth:
 * the same file ships in the repo and powers this in-app, browsable page. The
 * base URL placeholder is personalised to this install.
 *
 * §4 audit (2026-09-13, theme-integrity blueprint): content/examples were
 * verified copy-paste-correct against the live controllers (Catalogue/Quote/
 * Order) — the one real gap found was the 409 duplicate_in_progress response
 * being undocumented (fixed in the markdown itself). Information architecture
 * is otherwise sound (concepts before endpoints, quick-start last) but had
 * zero in-page navigation for a 270+ line single page — this build adds a
 * sticky table of contents from the doc's own `## ` headings, so it can never
 * drift out of sync with the actual section list.
 */
class DeveloperDocsController extends Controller
{
    public function __invoke()
    {
        [$html, $toc] = Cache::remember('developer-api-docs-html-v2', now()->addHour(), function () {
            $path = base_path('docs/DEVELOPER-API.md');
            $markdown = is_file($path)
                ? file_get_contents($path)
                : "# Developer API\n\nDocumentation is being prepared.";

            // Personalise the base-URL placeholder to this install.
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'your-domain';
            $markdown = str_replace('YOUR-DOMAIN', $host, $markdown);

            $toc = $this->tableOfContents($markdown);

            // Str::markdown() uses the GitHub-flavored converter, so the
            // reference's tables + fenced code render as expected.
            $html = Str::markdown($markdown);
            $html = $this->injectHeadingAnchors($html, $toc);

            return [$html, $toc];
        });

        return view('pages.developers', ['html' => $html, 'toc' => $toc]);
    }

    /**
     * Parse the doc's own `## N. Title` lines into a TOC, so the nav can never
     * silently drift from the actual sections — add a heading in the .md file
     * and it appears here automatically, no second place to update.
     *
     * @return list<array{slug: string, title: string}>
     */
    private function tableOfContents(string $markdown): array
    {
        preg_match_all('/^## (.+)$/m', $markdown, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $title) => [
                'slug' => $this->slug($title),
                // Keep the numbering ("1. Base URL") in the TOC label — it
                // doubles as a rough progress indicator while reading.
                'title' => trim($title),
            ])
            ->all();
    }

    /** "3. Money model (what you pay)" -> "money-model-what-you-pay" (matches the existing #authentication hero jump-link). */
    private function slug(string $heading): string
    {
        return Str::slug(preg_replace('/^\d+\.\s*/', '', $heading));
    }

    /**
     * Give each rendered `<h2>` the SAME id an anchor link expects, in
     * document order — the compiled HTML carries no ids by default (GFM
     * conversion here has no heading-permalink extension installed). Matched
     * positionally against $toc (built from the same headings, same order),
     * not by re-parsing rendered text, since Markdown inline formatting
     * (`` `code` ``, **bold**) would make a text-based match fragile.
     */
    private function injectHeadingAnchors(string $html, array $toc): string
    {
        $i = 0;

        return preg_replace_callback('/<h2>/', function () use (&$i, $toc) {
            $slug = $toc[$i]['slug'] ?? null;
            $i++;

            return $slug ? '<h2 id="'.$slug.'">' : '<h2>';
        }, $html);
    }
}
