<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Nothing in the admin panel may be reachable only by typing a URL. Every named admin page that opens on GET must either appear in the
 * admin navigation (resources/views/components/layouts/admin.blade.php) or be on the explicit list below of pages that are legitimately
 * not nav destinations (auth flows, detail pages reached from a list, file downloads). A new admin page that is forgotten fails here.
 */
class AdminNavReachabilityTest extends TestCase
{
    /** Pages that are intentionally not top-level navigation entries. */
    private const NOT_NAV = [
        'admin.login' => 'auth flow',
        'admin.recover' => 'auth flow',
        'admin.nci.provider' => 'detail page opened from the Provider Registry list',
        'admin.white-label.intake.pdf' => 'file download',
    ];

    public function test_every_admin_page_is_in_the_navigation_or_explicitly_exempt(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/admin.blade.php'));
        preg_match_all("/'route'\s*=>\s*'([a-z0-9_.\-]+)'/", $layout, $m);
        $listed = array_flip($m[1]);

        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (isset($listed[$name]) || isset(self::NOT_NAV[$name]) || str_contains($route->uri(), '{') && ! str_contains($route->uri(), '{adminGateway}')) {
                continue;
            }
            // Routes with a required parameter beyond the gateway are detail pages.
            if (preg_match('/\{(?!adminGateway)[^}?]+\}/', $route->uri())) {
                continue;
            }
            $missing[] = $name;
        }

        $this->assertSame([], $missing, 'Admin pages missing from the navigation: '.implode(', ', $missing));
    }

    public function test_security_is_a_bottom_bar_item_not_a_fifth_item_phones_cannot_show(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/admin.blade.php'));
        $shell = file_get_contents(resource_path('views/components/app-shell.blade.php'));

        // Security is added to $primary before the 4-slot cut, so it lands in the bottom bar for every role that has fewer than 4 before it.
        $this->assertMatchesRegularExpression("/\\\$primary\[\]\s*=\s*\['route' => 'admin\.security'/", $layout);
        // And whatever overflows the 4 slots leads the More sheet rather than vanishing.
        $this->assertStringContainsString('$moreForSheet', $shell);
        $this->assertStringContainsString('@foreach ($moreForSheet as $item)', $shell);
    }
}
