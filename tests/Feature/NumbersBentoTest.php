<?php

namespace Tests\Feature;

use App\Livewire\GetNumber;
use App\Models\NumbersBentoCard;
use App\Models\User;
use App\Support\NumbersBento;
use Database\Seeders\NumbersBentoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The six-card Numbers bento (Numbers V6 §1–2): fixed order, live verify count,
 * admin visibility toggle, and it renders on the Numbers landing.
 */
class NumbersBentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_render_in_the_asymmetric_bento_order_with_spans(): void
    {
        $cards = NumbersBento::cards();

        $this->assertSame(
            ['verify', 'rent', 'line', 'internet_calls', 'call_forwarding', 'contact_management'],
            array_column($cards, 'key'),
        );

        // Row 1: Verify (4) + Rent (2). Naara Line is full-width (span 6), so
        // is Contact Management; Internet Calls + Call Forwarding are halves (3).
        $bySpan = collect($cards)->keyBy('key');
        $this->assertSame(4, $bySpan['verify']['span']);
        $this->assertTrue($bySpan['verify']['tall']);
        $this->assertSame(2, $bySpan['rent']['span']);
        $this->assertSame(6, $bySpan['line']['span']);
        $this->assertSame(6, $bySpan['contact_management']['span']);
        $this->assertSame(3, $bySpan['internet_calls']['span']);
    }

    public function test_verify_card_appends_a_live_plus_n_more_bullet(): void
    {
        $verify = collect(NumbersBento::cards())->firstWhere('key', 'verify');
        $last = end($verify['bullets']);
        $this->assertMatchesRegularExpression('/^\+\d+ more$/', $last);
    }

    public function test_an_admin_can_toggle_a_card_off(): void
    {
        $this->seed(NumbersBentoSeeder::class);

        NumbersBentoCard::where('key', 'rent')->update(['is_active' => false]);
        NumbersBento::flush();

        $keys = array_column(NumbersBento::cards(), 'key');
        $this->assertNotContains('rent', $keys);
        $this->assertContains('verify', $keys);
    }

    public function test_the_internet_calls_copy_does_not_claim_a_line_is_required(): void
    {
        // Source-of-truth: calling is wallet-funded, not Line-gated.
        $card = collect(NumbersBento::cards())->firstWhere('key', 'internet_calls');
        $this->assertStringNotContainsStringIgnoringCase('naara line', $card['subtitle']);
        $this->assertStringContainsStringIgnoringCase('wallet', $card['subtitle']);
    }

    public function test_the_bento_renders_on_the_numbers_landing(): void
    {
        // Titles render two-tone (word-split across spans), so assert on the
        // contiguous subtitles instead.
        Livewire::actingAs(User::factory()->create())->test(GetNumber::class)
            ->assertSee('Receive OTPs and verification codes')
            ->assertSee('permanent international number')
            ->assertSee('address book')
            ->assertSeeInOrder(['Naara', 'Verify']);
    }

    public function test_a_deep_linked_modal_opens_with_the_right_request_type(): void
    {
        // "Get a Naara Line" (e.g. from Call forwarding / Send message) links to
        // ?modal=line — mount must open it as a permanent-number request.
        Livewire::withQueryParams(['modal' => 'line'])
            ->actingAs(User::factory()->create())->test(GetNumber::class)
            ->assertSet('modal', 'line')
            ->assertSet('type', \App\Services\SMS\NumberRequest::TYPE_PERMANENT);

        // An unknown modal value is dropped (no broken half-open state).
        Livewire::withQueryParams(['modal' => 'garbage'])
            ->actingAs(User::factory()->create())->test(GetNumber::class)
            ->assertSet('modal', '');
    }

    public function test_an_admin_can_edit_a_card_and_it_reaches_the_landing(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\NumbersBento::class)
            ->set('form.verify.title', 'Instant Verify')
            ->set('form.verify.subtitle', 'Codes in seconds.')
            ->call('save', 'verify')
            ->assertHasNoErrors()
            ->assertSet('saved', 'verify');

        NumbersBento::flush();
        $card = collect(NumbersBento::cards())->firstWhere('key', 'verify');
        $this->assertSame('Instant Verify', $card['title']);
        $this->assertSame('Codes in seconds.', $card['subtitle']);
    }

    public function test_the_bento_admin_is_super_admin_gated(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create(); // no admin role

        Livewire::actingAs($user)->test(\App\Livewire\Admin\NumbersBento::class)
            ->assertForbidden();
    }
}
