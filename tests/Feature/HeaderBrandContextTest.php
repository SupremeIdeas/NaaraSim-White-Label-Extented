<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BrandContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Route-aware header branding (owner request): the umbrella Naara family mark
 * everywhere on the dashboard EXCEPT a product's own surface, where the header
 * wears that product's mark so it stands out — NaaraSim on the eSIM/number
 * surfaces, Naara Gift on the gift storefront. The mark lives in the header
 * chrome, never "down" in the page body.
 */
class HeaderBrandContextTest extends TestCase
{
    use RefreshDatabase;

    /** Resolve the header logo as if the given route were the current one. */
    private function logoOn(string $name): array
    {
        Route::get('/__probe', fn () => BrandContext::headerLogo())->name($name)->middleware('web');

        return $this->get('/__probe')->json();
    }

    public function test_esim_and_number_surfaces_wear_the_naarasim_mark(): void
    {
        foreach (['catalogue', 'numbers', 'numbers.dialer', 'numbers.contacts'] as $route) {
            $logo = $this->logoOn($route);
            $this->assertSame('product', $logo['variant'], "$route should wear the NaaraSim mark");
            $this->assertSame('NaaraSim', $logo['label']);
        }
    }

    public function test_the_gift_storefront_wears_the_naara_gift_mark(): void
    {
        $logo = $this->logoOn('gift-cards');
        $this->assertSame('gift', $logo['variant']);
        $this->assertSame('Naara Gift', $logo['label']);
    }

    public function test_everywhere_else_wears_the_naara_family_mark(): void
    {
        foreach (['dashboard', 'wallet', 'rewards', 'support', 'account'] as $route) {
            $logo = $this->logoOn($route);
            $this->assertSame('family', $logo['variant'], "$route should wear the umbrella Naara mark");
            $this->assertNull($logo['label']);
        }
    }

    public function test_the_catalogue_header_shows_the_naarasim_mark_not_the_family_mark(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $this->actingAs($user)->get(route('catalogue'))
            ->assertOk()
            ->assertSee('naarasim-product', false)   // header swapped to the product mark
            ->assertDontSee('naara-family', false);  // …and the umbrella mark is gone here
    }

    public function test_the_dashboard_header_shows_the_family_mark(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('naara-family', false);
    }
}
