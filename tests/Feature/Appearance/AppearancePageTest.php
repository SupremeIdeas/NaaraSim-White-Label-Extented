<?php

namespace Tests\Feature\Appearance;

use App\Livewire\Account\Appearance;
use App\Models\UserAppearance;
use App\Support\Appearance\AppearanceResolver;
use Livewire\Livewire;

class AppearancePageTest extends AppearanceTestCase
{
    public function test_picking_things_saves_to_the_account_and_pushes_the_result_to_the_page(): void
    {
        $u = $this->member();
        Livewire::actingAs($u)->test(Appearance::class)
            ->call('setAccent', 'violet')->assertDispatched('nx-appearance', accent: 'violet')
            ->call('setMode', 'light')->assertDispatched('nx-appearance', mode: 'light')
            ->call('setDial', 'ts', 'l')
            ->call('setDial', 'motion', 'reduced')
            ->call('setHex', '#10B981')->assertDispatched('nx-appearance', accent: 'custom');

        $row = UserAppearance::where('user_id', $u->id)->first();
        $this->assertSame(['custom', '#10b981', 'light', 'l', 'reduced'], [$row->accent_key, $row->accent_hex, $row->mode, $row->ts, $row->motion]);
    }

    public function test_resetting_dials_leaves_skin_accent_and_mode_alone(): void
    {
        $u = $this->member();
        Livewire::actingAs($u)->test(Appearance::class)
            ->call('setAccent', 'ocean')->call('setMode', 'dark')->call('setDial', 'round', 'sharp')->call('setDial', 'font', 'mono')
            ->call('resetDials');

        $r = AppearanceResolver::for($u);
        $this->assertSame(['ocean', 'dark', 'def', 'naara'], [$r['accent'], $r['mode'], $r['dials']['round'], $r['dials']['font']]);
    }

    public function test_an_invalid_hex_is_refused_with_a_toast_and_nothing_changes(): void
    {
        $u = $this->member();
        Livewire::actingAs($u)->test(Appearance::class)->call('setHex', 'purple')->assertDispatched('nx-toast', type: 'error');
        $this->assertSame(0, UserAppearance::count());
    }

    public function test_a_locked_page_says_so_and_refuses_changes(): void
    {
        $this->setting(AppearanceResolver::LOCK, true);
        $u = $this->member();
        Livewire::actingAs($u)->test(Appearance::class)
            ->assertSee(__('appearance.locked'))
            ->call('setAccent', 'ocean')->assertDispatched('nx-toast', type: 'error');
        $this->assertSame(0, UserAppearance::count());
    }

    public function test_only_available_skins_are_listed_and_the_custom_swatch_follows_the_admin_switch(): void
    {
        $u = $this->member();
        $c = Livewire::actingAs($u)->test(Appearance::class);
        $c->assertSee('Naara Surface')->assertDontSee('Neon Lagos')->assertSee(__('appearance.custom'));

        $this->setting(AppearanceResolver::ALLOW_CUSTOM, false);
        Livewire::actingAs($u)->test(Appearance::class)->assertDontSee(__('appearance.custom_hint', ['default' => 'x']));
    }

    public function test_every_skin_card_carries_its_own_key_and_the_default_is_marked(): void
    {
        $this->builtSkins(['calm', 'neo']);
        $html = Livewire::actingAs($this->member())->test(Appearance::class)->html();

        foreach (['surface', 'calm', 'neo'] as $key) {
            $this->assertStringContainsString("setSkin('{$key}')", $html);
            $this->assertStringContainsString('data-nx-preview="'.$key.'"', $html);
        }
        $this->assertStringNotContainsString("setSkin('0')", $html, 'grouping must keep the skin keys, not renumber them');
        $this->assertSame(1, substr_count($html, 'ns-skin-card is-selected'), 'exactly the applied skin is selected');
    }

    public function test_the_more_menu_lists_appearance(): void
    {
        $u = $this->member();
        $labels = collect(\App\Support\BottomNav::eligibleMainItems($u)['more'])->pluck('label');
        $this->assertTrue($labels->contains('Appearance'));
    }
}
