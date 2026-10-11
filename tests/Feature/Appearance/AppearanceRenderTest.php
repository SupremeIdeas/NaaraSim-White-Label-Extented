<?php

namespace Tests\Feature\Appearance;

use App\Models\UserAppearance;
use App\Support\Appearance\UpdateUserAppearance;
use Illuminate\Support\Facades\Cache;

/** Isolation + rendering: the attributes reach only the signed-in dashboard, per account, on every response. */
class AppearanceRenderTest extends AppearanceTestCase
{
    public function test_the_dashboard_html_carries_the_members_own_attributes(): void
    {
        $a = $this->member();
        $b = $this->member();
        (new UpdateUserAppearance)($a, ['accent' => 'ocean', 'mode' => 'light', 'dens' => 'compact']);

        $htmlA = $this->actingAs($a)->get(route('account.appearance'))->assertOk()->getContent();
        $htmlB = $this->actingAs($b)->get(route('account.appearance'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*data-nx-skin="surface"[^>]*data-nx-accent="ocean"[^>]*data-nx-mode="light"/', $htmlA);
        $this->assertStringContainsString('data-nx-dens="compact"', $htmlA);
        $this->assertStringContainsString('data-nx-user="1"', $htmlA);
        $this->assertMatchesRegularExpression('/<html[^>]*data-nx-accent="teal"/', $htmlB);
        $this->assertStringNotContainsString('data-nx-mode=', explode('</head>', $htmlB)[0] === '' ? '' : (preg_match('/<html[^>]*>/', $htmlB, $m) ? $m[0] : ''), 'a member with no saved mode follows this browser');
        $this->assertStringNotContainsString('data-nx-dens="compact"', $htmlB);
    }

    public function test_marketing_and_guest_pages_never_carry_the_attributes(): void
    {
        $guest = $this->get('/')->getContent();
        $this->assertStringNotContainsString('data-nx-skin', $guest);

        $member = $this->member();
        (new UpdateUserAppearance)($member, ['accent' => 'rose']);
        $signedIn = $this->actingAs($member)->get('/')->getContent();
        $this->assertStringNotContainsString('data-nx-accent', $signedIn, 'even a signed-in member sees the platform look on marketing pages');
    }

    public function test_a_custom_accent_emits_both_mode_variants_and_the_brand_primary_mapping(): void
    {
        $u = $this->member();
        (new UpdateUserAppearance)($u, ['accent' => 'custom', 'accent_hex' => '#8b5cf6']);

        $html = $this->actingAs($u)->get(route('account.appearance'))->getContent();
        $this->assertStringContainsString('id="nx-accent-vars"', $html);
        $this->assertStringContainsString('html[data-nx-accent=custom]{', $html);
        $this->assertStringContainsString('html.dark[data-nx-accent=custom]{', $html);
        $this->assertStringContainsString('--brand-primary:', $html);
        preg_match('/<style id="nx-accent-vars">(.*?)<\/style>/s', $html, $block);
        $this->assertStringNotContainsString('--brand-accent', $block[1] ?? '', 'gold (money) is never touched');
    }

    public function test_cache_keys_are_per_user_never_global(): void
    {
        $a = $this->member();
        $b = $this->member();
        (new UpdateUserAppearance)($a, ['accent' => 'ocean']);
        $this->actingAs($a)->get(route('account.appearance'));
        $this->actingAs($b)->get(route('account.appearance'));

        $this->assertTrue(Cache::has("appearance:user:{$a->id}"));
        $this->assertTrue(Cache::has("appearance:user:{$b->id}"));
        $this->assertNotEquals(Cache::get("appearance:user:{$a->id}"), Cache::get("appearance:user:{$b->id}"));
    }

    public function test_the_mode_endpoint_saves_to_the_account_and_validates(): void
    {
        $u = $this->member();
        $this->actingAs($u)->postJson(route('account.appearance.mode'), ['mode' => 'light'])->assertOk()->assertJson(['ok' => true, 'mode' => 'light']);
        $this->assertSame('light', UserAppearance::where('user_id', $u->id)->value('mode'));

        $this->actingAs($u)->postJson(route('account.appearance.mode'), ['mode' => 'system'])->assertStatus(422);
        auth()->logout();
        $this->postJson(route('account.appearance.mode'), ['mode' => 'dark'])->assertStatus(401);
    }

    public function test_the_admin_panel_renders_the_admins_own_appearance(): void
    {
        $adm = $this->admin('super_admin');
        (new UpdateUserAppearance)($adm, ['accent' => 'emerald']);

        $html = $this->actingAs($adm)->get(route('admin.my-appearance', ['adminGateway' => config('admin.path')]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*data-nx-accent="emerald"/', $html);
    }
}
