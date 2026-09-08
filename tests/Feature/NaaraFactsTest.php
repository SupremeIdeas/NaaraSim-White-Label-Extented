<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\User;
use App\Support\NaaraFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dashboard greeting + fact-of-the-day (owner request). Greets the user by name,
 * asks about their day, and surfaces one stable-per-day fact about what their
 * NaaraSim numbers/eSIMs can do.
 */
class NaaraFactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_greeting_uses_the_first_name_and_time_of_day(): void
    {
        $user = User::factory()->create(['name' => 'Ada Obi']);

        Carbon::setTestNow(Carbon::today()->setHour(9));
        $this->assertSame('Good morning, Ada', NaaraFacts::greeting($user));

        Carbon::setTestNow(Carbon::today()->setHour(14));
        $this->assertSame('Good afternoon, Ada', NaaraFacts::greeting($user));

        Carbon::setTestNow(Carbon::today()->setHour(20));
        $this->assertSame('Good evening, Ada', NaaraFacts::greeting($user));

        Carbon::setTestNow();
    }

    public function test_the_fact_is_stable_within_a_day_and_rotates_across_days(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-07-21 08:00'));
        $day1a = NaaraFacts::dailyFor($user);
        $day1b = NaaraFacts::dailyFor($user); // same day → identical
        $this->assertSame($day1a, $day1b);
        $this->assertContains($day1a, NaaraFacts::FACTS);

        // A run of days should surface more than one fact (rotation works).
        $seen = [];
        foreach (range(0, 20) as $d) {
            Carbon::setTestNow(Carbon::parse('2026-07-21 08:00')->addDays($d));
            $seen[NaaraFacts::dailyFor($user)] = true;
        }
        $this->assertGreaterThan(1, count($seen));

        Carbon::setTestNow();
    }

    public function test_the_dashboard_renders_the_greeting_and_a_fact(): void
    {
        $user = User::factory()->create(['name' => 'Ada Obi', 'is_active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Ada')
            ->assertSee('Did you know?');
    }
}
