<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Localization Phase A: locale resolution reuses the existing (previously
 * dead) users.language column, mirrors LocaleCurrency's priority chain
 * exactly, and only ever resolves to a locale with real, shipped
 * translations — never a "coming soon" one, even if session/DB data is
 * tampered with directly.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        session()->forget(Locale::SESSION_KEY);
        parent::tearDown();
    }

    public function test_resolves_to_english_with_no_signals_at_all(): void
    {
        $this->assertSame('en', Locale::resolve());
    }

    public function test_an_unshipped_locale_never_resolves_even_from_a_tampered_session(): void
    {
        session([Locale::SESSION_KEY => 'fr']);

        $this->assertSame('en', Locale::resolve());
    }

    public function test_session_choice_wins_over_the_users_saved_language(): void
    {
        $user = User::factory()->create(['language' => 'en']);
        session([Locale::SESSION_KEY => 'en']);

        $this->assertSame('en', Locale::resolve($user));
    }

    public function test_a_tampered_user_language_column_never_resolves_if_unavailable(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['language' => 'ar'])->save();

        $this->assertSame('en', Locale::resolve($user));
    }

    public function test_country_code_guess_is_ignored_while_that_locale_is_unavailable(): void
    {
        // Senegal would map to French once it ships — today it must not.
        $user = User::factory()->create(['language' => null, 'country_code' => 'SN']);

        $this->assertSame('en', Locale::resolve($user));
    }

    public function test_choose_persists_to_session_and_the_users_language_column(): void
    {
        $user = User::factory()->create();

        $resolved = Locale::choose($user, 'en');

        $this->assertSame('en', $resolved);
        $this->assertSame('en', session(Locale::SESSION_KEY));
        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_choose_downgrades_an_unavailable_locale_to_english(): void
    {
        $user = User::factory()->create();

        $resolved = Locale::choose($user, 'ar');

        $this->assertSame('en', $resolved);
        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_available_only_lists_shipped_locales(): void
    {
        $available = Locale::available();

        $this->assertArrayHasKey('en', $available);
        $this->assertArrayNotHasKey('fr', $available);
        $this->assertArrayNotHasKey('ar', $available);
    }

    public function test_options_lists_the_full_roadmap_for_an_honest_switcher(): void
    {
        $options = Locale::options();

        $this->assertArrayHasKey('fr', $options);
        $this->assertFalse($options['fr']['available']);
        $this->assertTrue($options['ar']['rtl']);
    }

    public function test_the_middleware_applies_the_resolved_locale_to_the_request(): void
    {
        $user = User::factory()->create(['language' => 'en']);

        $this->actingAs($user)->get(route('account'))->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_profile_page_lets_a_user_choose_from_the_available_locales(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(\App\Livewire\Profile::class)
            ->assertSet('language', 'en')
            ->set('language', 'en')
            ->call('save');

        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_profile_page_rejects_a_language_that_has_not_shipped(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(\App\Livewire\Profile::class)
            ->set('language', 'fr')
            ->call('save')
            ->assertHasErrors(['language']);
    }
}
