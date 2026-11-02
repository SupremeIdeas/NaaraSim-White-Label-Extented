<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Every error page: premium shell, self-contained, no leaks, a quotable reference on a server error. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);   // production behaviour: the friendly page, never the debug trace
    }

    private function assertPremiumAndSafe(string $html): void
    {
        $this->assertStringContainsString('name="viewport"', $html);
        $this->assertStringContainsString('class="tile"', $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);
        $this->assertDoesNotMatchRegularExpression('#(<script[^>]+src=|<link[^>]+href=|<img[^>]+src=|@import|url\(\s*[\'"]?https?:)#i', $html, 'the page must not load anything from outside');
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html, 'icons are SVG only, no emoji');
        foreach (['Stack trace', 'vendor/laravel', '.php:', 'Illuminate\\'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
        @mkdir('/tmp/err/out', 0777, true);   // the pages are also written out so they can be screenshotted
    }

    public function test_every_error_status_renders_the_shell(): void
    {
        Route::get('/__e/403', fn () => abort(403));
        Route::get('/__e/401', fn () => abort(401));
        Route::get('/__e/429', fn () => abort(429, '', ['Retry-After' => 12]));
        Route::get('/__e/503', fn () => abort(503));
        $expect = ['/nope/nowhere' => [404, 'We can’t find that page'], '/__e/403' => [403, 'not open to your account'], '/__e/401' => [401, 'sign in'],
'/__e/429' => [429, 'Too many requests'], '/__e/503' => [503, 'quick tune-up']];
        foreach ($expect as $uri => [$status, $text]) {
            $res = $this->get($uri);
            $res->assertStatus($status);
            $this->assertStringContainsStringIgnoringCase($text, $res->getContent(), $uri);
            $this->assertPremiumAndSafe($res->getContent());
            file_put_contents('/tmp/err/out/'.$status.'.html', $res->getContent());
        }
        // 419 is turned into a redirect by the app's own handler for browsers, so the view is rendered directly.
        $html419 = view('errors.419')->render();
        $this->assertStringContainsString('Let’s try that again', $html419);
        $this->assertPremiumAndSafe($html419);
        file_put_contents('/tmp/err/out/419.html', $html419);
        $this->assertStringContainsString('id="n">12<', $this->get('/__e/429')->getContent(), 'the 429 page counts down from Retry-After');
        $this->assertStringContainsString('http-equiv="refresh"', $this->get('/__e/503')->getContent());
    }

    public function test_a_server_error_shows_a_reference_that_matches_the_log_and_leaks_nothing(): void
    {
        Route::get('/__e/boom', fn () => throw new \RuntimeException('secret internals /var/www/app/Thing.php'));
        $res = $this->get('/__e/boom');
        $res->assertStatus(500);
        $html = $res->getContent();
        $this->assertPremiumAndSafe($html);
        $this->assertStringNotContainsString('secret internals', $html);
        $this->assertMatchesRegularExpression('/id="ref">(ERR-[A-Z0-9]+-[A-F0-9]{4})</', $html);
        preg_match('/id="ref">(ERR-[A-Z0-9]+-[A-F0-9]{4})</', $html, $m);
        $this->assertSame($m[1], ErrorLog::query()->latest('id')->first()->context['reference'], 'the reference on screen is the one in the error log');
        file_put_contents('/tmp/err/out/500.html', $html);
    }
}
