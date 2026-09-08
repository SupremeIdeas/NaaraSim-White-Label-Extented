<?php

namespace App\Http\Controllers;

use App\Models\CustomPage;

/**
 * Serves an admin-authored custom-HTML page at /p/{slug} inside the marketing
 * shell. Only published, non-reserved slugs resolve; everything else is a 404.
 */
class CustomPageController extends Controller
{
    public function __invoke(string $slug)
    {
        if (in_array($slug, CustomPage::RESERVED, true)) {
            abort(404);
        }

        $page = CustomPage::where('slug', $slug)->where('is_published', true)->first();
        abort_if($page === null, 404);

        return view('pages.custom', ['page' => $page]);
    }
}
