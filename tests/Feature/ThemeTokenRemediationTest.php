<?php

namespace Tests\Feature;

use App\Livewire\GetNumber;
use App\Livewire\PartnerEarnings;
use App\Livewire\Wallet;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Models\User;
use App\Support\ThemePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Theme-integrity blueprint §1.2/§1.3 — proves the specific hardcoded-color
 * bug it flagged (teal-as-primary-standin, `#243352`/`#0C2434`/`#081521`
 * literal hex bypassing --brand-card-dark) is actually gone from the three
 * files the blueprint named: wallet.blade.php, numbers-bento.blade.php,
 * partner-earnings.blade.php. Renders each under a PURPLE custom theme —
 * if the fix regresses, that literal `teal` text would still bleed through
 * a purple-themed page, which this test would catch.
 */
class ThemeTokenRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ThemePresetModel::create([
            'slug' => 'test-purple',
            'name' => 'Test Purple',
            'tokens' => ['colors' => [
                'primary' => '109 63 160', 'primary_dark' => '79 45 120',
                'accent' => '232 121 249', 'accent_dark' => '162 85 174',
                'navy' => '30 20 45', 'action' => '236 90 70',
            ]],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false,
            'sort_order' => 99,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'test-purple');
        ThemePreset::bust();
    }

    protected function tearDown(): void
    {
        ThemePreset::bust();
        parent::tearDown();
    }

    private function assertNoLiteralTealOrHex(\Livewire\Features\SupportTesting\Testable $component): void
    {
        $component
            ->assertDontSeeHtml('text-teal-')
            ->assertDontSeeHtml('bg-teal-')
            ->assertDontSeeHtml('border-teal-')
            ->assertDontSeeHtml('ring-teal-')
            ->assertDontSeeHtml('#243352')
            ->assertDontSeeHtml('#0C2434')
            ->assertDontSeeHtml('#081521');
    }

    public function test_wallet_carries_no_literal_teal_or_card_surface_hex(): void
    {
        $user = User::factory()->create();

        $this->assertNoLiteralTealOrHex(
            Livewire::actingAs($user)->test(Wallet::class)
        );
    }

    public function test_numbers_bento_carries_no_literal_teal_or_card_surface_hex(): void
    {
        $user = User::factory()->create();

        $this->assertNoLiteralTealOrHex(
            Livewire::actingAs($user)->test(GetNumber::class)
        );
    }

    public function test_partner_earnings_carries_no_literal_teal(): void
    {
        $owner = User::factory()->create();
        Partner::create([
            'owner_user_id' => $owner->id,
            'status' => Partner::ACTIVE,
            'profit_share_pct' => 10,
            'payout_cadence' => Partner::CADENCE_MONTHLY,
            'payout_mode' => Partner::MODE_MANUAL,
        ]);

        $this->assertNoLiteralTealOrHex(
            Livewire::actingAs($owner)->test(PartnerEarnings::class)
        );
    }
}
