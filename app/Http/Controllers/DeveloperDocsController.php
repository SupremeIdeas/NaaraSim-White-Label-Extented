<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public Developer API documentation page (ROADMAP §Layer 2). Renders the
 * canonical reference (docs/DEVELOPER-API.md) so there is ONE source of truth:
 * the same file ships in the repo and powers this in-app, browsable page. The
 * base URL placeholder is personalised to this install.
 */
class DeveloperDocsController extends Controller
{
    public function __invoke()
    {
        $html = Cache::remember('developer-api-docs-html', now()->addHour(), function () {
            $path = base_path('docs/DEVELOPER-API.md');
            $markdown = is_file($path)
                ? file_get_contents($path)
                : "# Developer API\n\nDocumentation is being prepared.";

            // Personalise the base-URL placeholder to this install.
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'your-domain';
            $markdown = str_replace('YOUR-DOMAIN', $host, $markdown);

            // Str::markdown() uses the GitHub-flavored converter, so the
            // reference's tables + fenced code render as expected.
            return Str::markdown($markdown);
        });

        return view('pages.developers', ['html' => $html]);
    }
}
