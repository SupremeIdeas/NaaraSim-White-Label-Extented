<?php

namespace Tests\Feature;

use App\Livewire\Dialer;
use App\Models\User;
use App\Support\DialCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Numbers overhaul §3 — the dialer country picker's dial-code source. Home
 * markets are pinned first; codes resolve for ISO2 and provider slugs alike.
 */
class DialerCountryPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_markets_are_pinned_to_the_top_in_order(): void
    {
        $rows = DialCodes::all();
        $isos = array_map(fn ($r) => $r['iso'], array_slice($rows, 0, 6));

        $this->assertSame(['ng', 'gh', 'ke', 'za', 'us', 'gb'], $isos);
        $this->assertSame('234', $rows[0]['code']);
        $this->assertNotEmpty($rows[0]['name']);
    }

    public function test_code_resolves_for_iso_and_slug(): void
    {
        $this->assertSame('234', DialCodes::codeFor('ng'));
        $this->assertSame('234', DialCodes::codeFor('nigeria'));
        $this->assertSame('1', DialCodes::codeFor('usa'));
        $this->assertNull(DialCodes::codeFor('atlantis'));
    }

    public function test_default_falls_back_to_nigeria_for_unknown_country(): void
    {
        $this->assertSame('gh', DialCodes::default('gh')['iso']);
        $this->assertSame('ng', DialCodes::default(null)['iso']);
        $this->assertSame('ng', DialCodes::default('atlantis')['iso']);
    }

    public function test_the_rest_of_the_list_is_alphabetical_by_name(): void
    {
        $rows = DialCodes::all();
        $rest = array_slice($rows, 6);
        $names = array_map(fn ($r) => $r['name'], $rest);
        $sorted = $names;
        sort($sorted, SORT_STRING);

        $this->assertSame($sorted, $names);
    }

    public function test_the_dialer_renders_the_country_picker_and_wallet(): void
    {
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
        $user = User::factory()->create(['country_code' => 'GH']);

        Livewire::actingAs($user)->test(Dialer::class)
            ->assertOk()
            ->assertSee('Nigeria')     // a picker row
            ->assertSee('+233')        // the caller's own (Ghana) code, defaulted
            ->assertSee('Search country or code');
    }
}
