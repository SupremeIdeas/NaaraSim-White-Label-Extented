<?php

namespace Tests\Feature;

use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\LogWebhookDelivery;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottleUnprotectedAuthRoutes;
use App\Http\Middleware\VerifyTurnstile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Component;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

/**
 * A Livewire action is a POST to /livewire/update, and Livewire only re-runs the route middleware named in its persistent list. If the app's own gates are
 * missing from that list, a person who loses access keeps every action on a page they already opened (a demoted admin, a paused account).
 * Registered in AppServiceProvider::boot; this proves it holds and fails when a new gate is added to a page without being registered.
 */
class LivewireGatesPersistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_app_gates_are_in_livewires_persistent_list(): void
    {
        $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();
        foreach ([
            EnsureAdmin::class, EnsureActive::class,
            RoleMiddleware::class, PermissionMiddleware::class,
        ] as $gate) {
            $this->assertContains($gate, $persistent, $gate.' must be re-run on Livewire updates');
        }
    }

    /** Open an admin page, lose the role, then send an action with the snapshot the page gave us. */
    public function test_a_demoted_admin_cannot_keep_acting_on_an_open_admin_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $url = route('admin.my-appearance', ['adminGateway' => config('admin.path')]);

        $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null, 'the page carries a Livewire snapshot');
        $payload = ['components' => [['snapshot' => html_entity_decode($m[1], ENT_QUOTES), 'updates' => (object) [], 'calls' => []]]];
        $headers = ['X-Livewire' => 'true', 'Accept' => 'application/json'];

        $this->actingAs($admin->fresh())->postJson('/livewire/update', $payload, $headers)->assertOk();   // still an admin: accepted

        $admin->removeRole('admin');
        $this->actingAs($admin->fresh())->postJson('/livewire/update', $payload, $headers)->assertStatus(404);
    }

    /** Every middleware on every Livewire page is re-run on its actions, or is on this short list of things that do not decide who may act. */
    public function test_every_gate_on_every_livewire_page_is_re_run_on_its_actions(): void
    {
        $doesNotDecideAccess = [
            EncryptCookies::class, AddQueuedCookiesToResponse::class,
            StartSession::class, ShareErrorsFromSession::class,
            ValidateCsrfToken::class, ThrottleRequests::class,
            RedirectIfNotInstalled::class, SetLocale::class, CaptureReferral::class,
            SecurityHeaders::class, VerifyTurnstile::class,
            ThrottleUnprotectedAuthRoutes::class, LogWebhookDelivery::class,
        ];
        $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();
        $router = app('router');
        $unguarded = [];
        $pages = 0;
        foreach ($router->getRoutes() as $route) {
            $class = strstr($route->getActionName(), '@', true) ?: $route->getActionName();
            if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
                continue;
            }
            $pages++;
            foreach ($router->gatherRouteMiddleware($route) as $m) {
                if (! is_string($m)) {
                    continue;
                }
                $mc = strstr($m, ':', true) ?: $m;
                if (! in_array($mc, $persistent, true) && ! in_array($mc, $doesNotDecideAccess, true)) {
                    $unguarded[$mc][] = $route->uri();
                }
            }
        }
        $this->assertGreaterThan(50, $pages);
        $this->assertSame([], array_map(fn ($u) => array_slice($u, 0, 3), $unguarded), 'These middleware gate a Livewire page but are not re-run on its actions (add them to Livewire::addPersistentMiddleware in AppServiceProvider):');
    }
}
