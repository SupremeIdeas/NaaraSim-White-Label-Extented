<?php

namespace Tests\Feature;

use App\Support\TestedProviders;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TestedProvidersTest extends TestCase
{
    public function test_only_providers_with_a_real_scope_and_date_are_listed_and_the_two_levels_are_honest(): void
    {
        foreach (TestedProviders::all() as $slug) {
            $t = TestedProviders::for($slug);
            $this->assertNotEmpty($t['tested'], $slug.' needs what was tested');
            $this->assertNotEmpty($t['not_yet'], $slug.' must say what was NOT tested');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}(-\d{2})?$/', $t['on']);
        }
        $this->assertTrue(TestedProviders::recommended('grey'));
        $this->assertFalse(TestedProviders::recommended('twilio'), 'credentials alone are "Tested live", not "Recommended"');
        $this->assertNull(TestedProviders::for('herosms'), 'a provider nobody has called live gets no badge');
    }

    public function test_the_badge_shows_recommended_only_for_proven_rails_and_nothing_for_untested_ones(): void
    {
        $proven = Blade::render('<x-ui.tested-badge provider="grey" :detail="true" />');
        $this->assertStringContainsString('Recommended', $proven);
        $this->assertStringContainsString('Tested live', $proven);
        $this->assertStringContainsString('Not yet:', $proven);
        $paga = Blade::render('<x-ui.tested-badge provider="paga" :detail="true" />');
        $this->assertStringContainsString('Latest re-test:', $paga, 'a failed re-test is shown next to the badge, never hidden');
        $connected = Blade::render('<x-ui.tested-badge provider="twilio" />');
        $this->assertStringNotContainsString('Recommended', $connected);
        $this->assertStringContainsString('Tested live', $connected);
        $this->assertSame('', trim(Blade::render('<x-ui.tested-badge provider="herosms" />')));
    }
}
