<?php

namespace Tests\Feature\Appearance;

use App\Models\AppearancePreset;
use App\Support\Appearance\AppearanceException;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Appearance\UpdateUserAppearance;
use Illuminate\Support\Facades\RateLimiter;

class AppearanceValidationTest extends AppearanceTestCase
{
    private function save($user, array $in): array
    {
        return (new UpdateUserAppearance)($user, $in);
    }

    public function test_unknown_disabled_and_unbuilt_keys_are_rejected_without_leaking_what_exists(): void
    {
        $u = $this->member();
        AppearancePreset::where('kind', 'accent')->where('key', 'rose')->update(['enabled' => false]);
        AppearanceResolver::forgetPlatform();

        foreach ([['skin' => 'nope'], ['skin' => 'calm'], ['accent' => 'rose'], ['accent' => 'nope'], ['mode' => 'purple'], ['ts' => 'huge'], ['font' => 'comic']] as $bad) {
            try {
                $this->save($u, $bad);
                $this->fail('should reject '.json_encode($bad));
            } catch (AppearanceException $e) {
                $this->assertSame((string) __('appearance.err.invalid'), $e->getMessage(), 'the same message for every refusal');
            }
        }
    }

    public function test_hex_rules(): void
    {
        $u = $this->member();
        foreach (['#12345', '123456', '#GGGGGG', '#12345678', ''] as $hex) {
            $threw = false;
            try {
                $this->save($u, ['accent' => 'custom', 'accent_hex' => $hex]);
            } catch (AppearanceException) {
                $threw = true;
            }
            $this->assertTrue($threw, "hex {$hex} must be rejected");
        }
        $this->expectException(AppearanceException::class);
        $this->save($u, ['accent_hex' => '#aabbcc']);   // a hex only ever travels with accent=custom
    }

    public function test_choosing_a_preset_is_also_the_reset_for_a_custom_colour(): void
    {
        $u = $this->member();
        $this->save($u, ['accent' => 'custom', 'accent_hex' => '#8b5cf6']);
        $r = $this->save($u, ['accent' => 'ocean']);

        $this->assertSame(['ocean', null], [$r['accent'], $r['accent_hex']]);
        $r = $this->save($u, ['accent' => null]);
        $this->assertSame('teal', $r['accent']);
    }

    public function test_null_clears_a_dial_back_to_the_default(): void
    {
        $u = $this->member();
        $this->save($u, ['depth' => 'deep']);
        $r = $this->save($u, ['depth' => null]);
        $this->assertSame('soft', $r['dials']['depth']);
    }

    public function test_it_is_throttled(): void
    {
        $u = $this->member();
        RateLimiter::clear('appearance-save:'.$u->id);
        for ($i = 0; $i < 30; $i++) {
            $this->save($u, ['mode' => $i % 2 ? 'dark' : 'light']);
        }
        $this->expectException(AppearanceException::class);
        $this->save($u, ['mode' => 'dark']);
    }

    public function test_a_locked_platform_refuses_member_writes_but_admins_may_write(): void
    {
        $this->setting(AppearanceResolver::LOCK, true);
        try {
            $this->save($this->member(), ['mode' => 'dark']);
            $this->fail('locked');
        } catch (AppearanceException $e) {
            $this->assertSame((string) __('appearance.err.locked'), $e->getMessage());
        }
        $this->assertSame('dark', $this->save($this->admin(), ['mode' => 'dark'])['mode']);
    }
}
