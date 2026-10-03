<?php

namespace Tests\Feature\Appearance;

use App\Livewire\Admin\SkinSelection;
use App\Models\AppearancePreset;
use App\Models\Setting;
use App\Models\UserAppearance as UserAppearanceRow;
use App\Services\Updater\WhiteLabelUpdateClient;
use App\Support\Appearance\AppearanceException;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Appearance\LicensedSkins;
use App\Support\Appearance\UpdateUserAppearance;
use App\Support\FeatureEntitlements;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Prompt 22 — the fork is a CONSUMER of the skin allowance. Master decides the number; this install stores it, lets the licensee pick
 * which skins fill it, and enforces the hidden ones on the server. No tier-to-count logic exists here (see WhiteLabelBoundaryFitnessTest).
 */
class LicensedSkinsTest extends AppearanceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ship a handful of skins so there is something to hide; no allowance has been received yet (a fresh, un-activated install).
        config(['appearance.built' => ['surface', 'glass', 'neo', 'passport', 'golden']]);
        config()->set('updater.product_identifier', 'naarasim-whitelabel');
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'test-token');
        FeatureEntitlements::bust();
        AppearanceResolver::forgetPlatform();
    }

    private function allowance(int $n): void
    {
        $this->assertTrue(LicensedSkins::storeAllowance($n));
    }

    public function test_before_activation_exactly_one_skin_is_offered_and_it_is_the_platform_default(): void
    {
        $this->assertFalse(LicensedSkins::activated());
        $this->assertSame(1, LicensedSkins::allowance());
        $this->assertSame(['surface'], LicensedSkins::available());
        $this->assertSame(['surface'], AppearanceResolver::platform()['skins']);
    }

    public function test_an_allowance_alone_still_offers_only_the_default_until_the_licensee_chooses(): void
    {
        $this->allowance(2);

        $this->assertTrue(LicensedSkins::activated());
        $this->assertSame(['surface'], LicensedSkins::available());
    }

    public function test_the_licensee_can_choose_up_to_the_allowance_and_the_first_is_the_default(): void
    {
        $this->allowance(2);

        $this->assertTrue(LicensedSkins::saveSelection(['glass', 'neo'])['ok']);

        $this->assertSame(['glass', 'neo'], LicensedSkins::available());
        $this->assertSame('glass', LicensedSkins::default());
        $platform = AppearanceResolver::platform();
        $this->assertSame(['glass', 'neo'], $platform['skins']);
        $this->assertSame('glass', $platform['skin']);
        $this->assertTrue((bool) AppearancePreset::where('key', 'glass')->value('is_default'));
        $this->assertFalse((bool) AppearancePreset::where('key', 'surface')->value('enabled'));
    }

    public function test_a_selection_over_the_allowance_or_outside_the_catalogue_is_rejected(): void
    {
        $this->allowance(2);

        $over = LicensedSkins::saveSelection(['glass', 'neo', 'passport']);
        $this->assertFalse($over['ok']);
        $this->assertSame([], LicensedSkins::chosen());

        $this->assertFalse(LicensedSkins::saveSelection(['glass', 'not-a-skin'])['ok']);
        $this->assertFalse(LicensedSkins::saveSelection([])['ok']);
    }

    public function test_a_member_cannot_save_a_hidden_skin_even_by_forging_the_request(): void
    {
        $this->allowance(2);
        LicensedSkins::saveSelection(['glass', 'neo']);
        $u = $this->member();

        $this->expectException(AppearanceException::class);
        (new UpdateUserAppearance)($u, ['skin' => 'passport']);
    }

    public function test_a_member_can_pick_any_licensed_skin(): void
    {
        $this->allowance(2);
        LicensedSkins::saveSelection(['glass', 'neo']);
        $u = $this->member();

        (new UpdateUserAppearance)($u, ['skin' => 'neo']);

        $this->assertSame('neo', AppearanceResolver::for($u->fresh())['skin']);
    }

    public function test_a_previously_saved_skin_that_is_now_hidden_resolves_to_the_default_and_the_row_is_untouched(): void
    {
        $this->allowance(2);
        LicensedSkins::saveSelection(['glass', 'neo']);
        $u = $this->member();
        UserAppearanceRow::create(['user_id' => $u->id, 'skin_key' => 'passport']);
        AppearanceResolver::forget($u->id);

        $this->assertSame('glass', AppearanceResolver::for($u->fresh())['skin']);
        $this->assertSame('passport', UserAppearanceRow::where('user_id', $u->id)->value('skin_key'), 'never rewrites the member\'s saved choice');
    }

    public function test_when_the_allowance_drops_the_selection_is_trimmed_and_the_default_is_kept(): void
    {
        $this->allowance(3);
        LicensedSkins::saveSelection(['neo', 'glass', 'passport']);

        $this->allowance(2);

        $this->assertSame(['neo', 'glass'], LicensedSkins::chosen());
        $this->assertSame('neo', LicensedSkins::default());
    }

    public function test_a_malformed_allowance_is_ignored_and_the_last_known_one_stays(): void
    {
        $this->allowance(2);

        foreach ([0, -1, 'many', null, [], 2.5, true] as $bad) {
            $this->assertFalse(LicensedSkins::storeAllowance($bad), var_export($bad, true));
        }
        $this->assertSame(2, LicensedSkins::allowance());
    }

    public function test_the_entitlement_refresh_stores_the_allowance_and_a_failed_refresh_keeps_it(): void
    {
        Http::fake(['master.example.test/api/v1/white-label/entitlement*' => Http::sequence()
            ->push(['level' => 'standard', 'locks' => [], 'skins' => ['allowance' => 2]])
            ->push(['message' => 'down'], 500)
            ->push(['level' => 'standard', 'locks' => []])                       // an older master with no skins block
            ->push(['level' => 'standard', 'locks' => [], 'skins' => ['allowance' => 'lots']]),
        ]);
        $client = app(WhiteLabelUpdateClient::class);

        $this->assertSame(2, $client->refreshEntitlement()['skins_allowance']);
        $this->assertSame(2, LicensedSkins::allowance());

        $this->assertFalse($client->refreshEntitlement()['ok']);
        $this->assertSame(2, LicensedSkins::allowance(), 'a failed refresh never wipes it');

        $client->refreshEntitlement();
        $this->assertSame(2, LicensedSkins::allowance(), 'no skins block changes nothing');

        $client->refreshEntitlement();
        $this->assertSame(2, LicensedSkins::allowance(), 'a malformed value changes nothing');
    }

    public function test_the_selection_screen_saves_a_valid_choice_and_blocks_a_third_pick(): void
    {
        $this->allowance(2);
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(SkinSelection::class)
            ->call('toggle', 'glass')->call('toggle', 'neo')
            ->call('toggle', 'passport')->assertSet('error', 'Your licence unlocks 2 skin(s). Untick one first.')
            ->call('makeDefault', 'neo')
            ->call('review')->assertSet('confirming', true)
            ->call('save')->assertSet('error', null);

        $this->assertSame(['neo', 'glass'], LicensedSkins::chosen());
    }

    public function test_the_selection_screen_cannot_pick_anything_before_activation(): void
    {
        Livewire::actingAs($this->admin())->test(SkinSelection::class)
            ->call('toggle', 'glass')->assertSet('picked', []);
    }

    public function test_a_non_admin_cannot_open_or_drive_the_selection_screen(): void
    {
        $this->allowance(2);

        $this->actingAs($this->member())->get(route('admin.skins'))->assertNotFound();   // the admin area answers non-admins with a plain 404
        Livewire::actingAs($this->member())->test(SkinSelection::class)->assertForbidden();
    }

    public function test_the_platform_appearance_page_cannot_widen_the_licence(): void
    {
        $this->allowance(2);
        LicensedSkins::saveSelection(['glass', 'neo']);

        Livewire::actingAs($this->admin())->test(\App\Livewire\Admin\UserAppearance::class)
            ->call('toggle', 'skin', 'passport');

        $this->assertSame(['glass', 'neo'], LicensedSkins::available());
        $this->assertFalse((bool) AppearancePreset::where('key', 'passport')->value('enabled'));
    }

    public function test_the_forks_own_settings_cannot_be_used_to_mint_an_allowance_by_a_non_master_path(): void
    {
        // Only storeAllowance (fed by the entitlement refresh) sets the number; the selection setting alone can never exceed it.
        $this->allowance(1);
        Setting::setValue(LicensedSkins::SELECTED_KEY, ['glass', 'neo', 'passport'], 'white_label');
        AppearanceResolver::forgetPlatform();

        $this->assertSame(['glass'], LicensedSkins::chosen());
        $this->assertSame(['glass'], LicensedSkins::available());
    }
}
