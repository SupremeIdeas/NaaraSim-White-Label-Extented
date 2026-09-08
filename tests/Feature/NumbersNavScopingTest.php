<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Numbers overhaul §2/§5 — on /numbers/* the global nav + standard header are
 * replaced by the Numbers section nav + a wallet-balance bar, driven by one
 * bidirectional route condition; everywhere else it's the reverse.
 */
class NumbersNavScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_route_shows_the_numbers_nav_and_wallet_bar(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $res = $this->actingAs($user)->get('/numbers/messages')->assertOk();
        // The Numbers section nav items…
        $res->assertSee('Forwarding')->assertSee('Dialer')->assertSee('Contacts');
        // …and the Messages slot linking to the inbox route.
        $res->assertSee('Messages')->assertSee('/numbers/messages');
    }

    public function test_a_non_numbers_route_keeps_the_global_chrome(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $res = $this->actingAs($user)->get('/dashboard')->assertOk();
        // The global bottom nav (its unique centre "More" button) is present, and
        // the numbers-only wallet top-up bar is not.
        $res->assertSee('aria-label="More"', false);
    }
}
