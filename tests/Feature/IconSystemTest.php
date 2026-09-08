<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\IconOverrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IconSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_icon_renders_the_inline_sprite_symbol_by_default(): void
    {
        $this->blade('<x-icon name="wallet" />')
            ->assertSee('href="#i-wallet"', false)
            ->assertSee('<use', false);
    }

    public function test_admin_override_replaces_the_sprite_with_a_custom_image(): void
    {
        Setting::setValue('ui.icon_overrides', "wallet = https://cdn.naarasim.com/icons/wallet.svg");

        $view = $this->blade('<x-icon name="wallet" />');
        $view->assertSee('src="https://cdn.naarasim.com/icons/wallet.svg"', false);
        $view->assertDontSee('href="#i-wallet"', false);
    }

    public function test_override_parser_slugs_keys_and_ignores_invalid_urls(): void
    {
        Setting::setValue('ui.icon_overrides', implode("\n", [
            'Wallet = https://cdn.example.com/w.svg',
            'gift = not-a-url',            // ignored (invalid URL)
            'no-equals-line',              // ignored (no =)
            'message circle = https://cdn.example.com/m.png',
        ]));
        IconOverrides::flush();

        $map = IconOverrides::all();
        $this->assertSame('https://cdn.example.com/w.svg', $map['wallet']);
        $this->assertArrayNotHasKey('gift', $map);
        $this->assertSame('https://cdn.example.com/m.png', $map['message-circle']);
    }

    public function test_saving_the_override_setting_busts_the_cache(): void
    {
        IconOverrides::all(); // prime cache (empty)
        Setting::setValue('ui.icon_overrides', 'bell = https://cdn.example.com/bell.svg');

        // No manual flush — the Setting::saved hook must have cleared it.
        $this->assertSame('https://cdn.example.com/bell.svg', IconOverrides::for('bell'));
    }

    public function test_no_emoji_anywhere_in_the_views(): void
    {
        $offenders = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u', $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Emoji found in views: '.implode(', ', $offenders));
    }
}
