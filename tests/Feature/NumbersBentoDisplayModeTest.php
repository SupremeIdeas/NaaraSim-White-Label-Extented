<?php

namespace Tests\Feature;

use App\Livewire\Admin\NumbersBento as AdminNumbersBento;
use App\Livewire\GetNumber;
use App\Models\NumbersBentoCard;
use App\Models\User;
use App\Support\NumbersBento;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner request 2026-09-08: admin should be able to choose, per bento card,
 * whether it opens as a modal or a dedicated page — mirroring how the eSIM
 * catalogue's purchase flow is already a dedicated page. Only verify/rent/line
 * are togglable (the other three keys were already dedicated pages and never
 * modal). Both modes must render the SAME underlying flow content
 * (partials.numbers-modal.{key}) — only the chrome differs — so nothing about
 * verify/rent/line's actual logic forks between modes.
 */
class NumbersBentoDisplayModeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_an_untouched_install_keeps_every_togglable_card_as_a_modal(): void
    {
        foreach (NumbersBento::TOGGLABLE as $key) {
            $this->assertFalse(NumbersBento::isPageMode($key));
        }

        $cards = collect(NumbersBento::cards())->keyBy('key');
        foreach (NumbersBento::TOGGLABLE as $key) {
            $this->assertArrayHasKey('modal', $cards[$key]['link']);
        }
    }

    public function test_a_non_togglable_key_is_never_page_mode_even_if_a_row_claims_it(): void
    {
        NumbersBentoCard::create([
            'key' => 'contact_management', 'title' => 'x', 'subtitle' => 'x',
            'is_active' => true, 'display_mode' => 'page',
        ]);

        $this->assertFalse(NumbersBento::isPageMode('contact_management'));
    }

    public function test_setting_a_card_to_page_mode_changes_its_bento_link_to_a_real_route(): void
    {
        NumbersBentoCard::create([
            'key' => 'rent', 'title' => 'Naara Rent', 'subtitle' => 'x',
            'is_active' => true, 'display_mode' => 'page',
        ]);
        NumbersBento::flush();

        $this->assertTrue(NumbersBento::isPageMode('rent'));

        $rent = collect(NumbersBento::cards())->firstWhere('key', 'rent');
        $this->assertSame('numbers', $rent['link']['route']);
        $this->assertSame(['modal' => 'rent'], $rent['link']['query']);
    }

    public function test_the_bento_grid_renders_a_real_navigable_link_for_a_page_mode_card(): void
    {
        NumbersBentoCard::create([
            'key' => 'rent', 'title' => 'Naara Rent', 'subtitle' => 'x',
            'is_active' => true, 'display_mode' => 'page',
        ]);
        NumbersBento::flush();

        $user = User::factory()->create();

        // Livewire::test renders the component directly, sidestepping whatever
        // else the real /numbers route's full middleware stack requires (email
        // verification mode, onboarding, etc.) — not what this test is about;
        // every other assertion here already goes through the same path.
        Livewire::actingAs($user)->test(GetNumber::class)
            ->assertSee(route('numbers', ['modal' => 'rent']), false);
    }

    public function test_the_numbers_page_hides_the_bento_grid_while_a_page_mode_flow_is_open(): void
    {
        NumbersBentoCard::create([
            'key' => 'verify', 'title' => 'Naara Verify', 'subtitle' => 'x',
            'is_active' => true, 'display_mode' => 'page',
        ]);
        NumbersBento::flush();

        $user = User::factory()->create();

        // "Make Internet Calls" is a bento-grid-only card title, never part of
        // the verify flow's own content — a real, meaningful marker that the
        // grid itself is gone, not a string that trivially never appears.
        Livewire::actingAs($user)->test(GetNumber::class, ['modal' => 'verify'])
            ->assertDontSee('Make Internet Calls')
            ->assertSee('Naara Verify');
    }

    public function test_the_numbers_page_still_overlays_the_modal_on_top_of_the_bento_grid_when_left_as_modal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(GetNumber::class, ['modal' => 'verify'])
            ->assertSee('role="dialog"', false);
    }

    // --- Admin screen ---

    public function test_a_non_admin_gets_a_403_on_the_numbers_bento_screen(): void
    {
        $this->seed(RoleSeeder::class);
        Livewire::actingAs(User::factory()->create())->test(AdminNumbersBento::class)->assertStatus(403);
    }

    public function test_an_admin_can_flip_rent_to_page_mode(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AdminNumbersBento::class)
            ->set('form.rent.display_mode', 'page')
            ->call('save', 'rent');

        $this->assertTrue(NumbersBento::isPageMode('rent'));
    }

    public function test_the_display_mode_field_only_shows_for_togglable_cards(): void
    {
        $html = Livewire::actingAs($this->admin())->test(AdminNumbersBento::class)->html();

        $this->assertSame(3, substr_count($html, 'wire:model="form.verify.display_mode"')
            + substr_count($html, 'wire:model="form.rent.display_mode"')
            + substr_count($html, 'wire:model="form.line.display_mode"'));
        $this->assertStringNotContainsString('form.contact_management.display_mode', $html);
        $this->assertStringNotContainsString('form.internet_calls.display_mode', $html);
        $this->assertStringNotContainsString('form.call_forwarding.display_mode', $html);
    }
}
