<?php

namespace App\Livewire\Account;

use App\Support\Appearance\AccentDeriver;
use App\Support\Appearance\AppearanceException;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Appearance\UpdateUserAppearance;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Account -> Appearance (Prompt 20 §18). Pick a skin, accent, mode and dials for YOUR account only. Every change goes
 * through UpdateUserAppearance (the one write path) and the resolved result is pushed to the open page so it changes
 * immediately, without a reload. An admin gets the same page for their own admin dashboard (Admin\MyAppearance).
 */
#[Layout('components.layouts.customer')]
class Appearance extends Component
{
    public string $hex = '#8b5cf6';

    public function mount(): void
    {
        $r = AppearanceResolver::for(Auth::user());
        $this->hex = $r['accent_hex'] ?? $this->hex;
    }

    public function setSkin(string $key): void
    {
        $this->save(['skin' => $key]);
    }

    public function setAccent(string $key): void
    {
        $this->save($key === 'custom' ? ['accent' => 'custom', 'accent_hex' => strtolower($this->hex)] : ['accent' => $key]);
    }

    public function setHex(string $hex): void
    {
        $hex = strtolower(trim($hex));
        if (! AccentDeriver::isValidHex($hex)) {
            $this->dispatch('nx-toast', type: 'error', message: (string) __('appearance.err.hex'));

            return;
        }
        $this->hex = $hex;
        $this->save(['accent' => 'custom', 'accent_hex' => $hex]);
    }

    public function resetAccent(): void
    {
        $this->save(['accent' => null]);   // back to the platform default accent; also clears any custom colour
    }

    public function setMode(string $mode): void
    {
        $this->save(['mode' => $mode]);
    }

    public function setDial(string $dial, string $value): void
    {
        $this->save([$dial => $value]);
    }

    public function resetDials(): void
    {
        $this->save(array_fill_keys(array_keys(config('appearance.dials')), null));
    }

    /** @param array<string, mixed> $input */
    private function save(array $input): void
    {
        try {
            $r = app(UpdateUserAppearance::class)(Auth::user(), $input);
        } catch (AppearanceException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->dispatch('nx-appearance', ...$this->payload($r));
        $this->dispatch('nx-toast', type: 'success', message: (string) __('appearance.saved'));
    }

    /** What the open page needs to restyle itself right now (mirrors the server-rendered <html> attributes). */
    private function payload(array $r): array
    {
        return [
            'skin' => $r['skin'], 'accent' => $r['accent'], 'mode' => $r['mode'], 'dials' => $r['dials'],
            'country' => $r['country'] ?? null, 'tod' => $r['tod'] ?? null,
            'css' => AppearanceResolver::customAccentCss($r),
        ];
    }

    protected function backRoute(): string
    {
        return route('account');
    }

    public function render()
    {
        $user = Auth::user();
        $r = AppearanceResolver::for($user);
        $platform = AppearanceResolver::platform();
        $skins = collect(config('appearance.skins'))->filter(fn ($s, $k) => in_array($k, $platform['skins'], true));
        $groups = $skins->groupBy('group', true)->sortBy(fn ($g, $name) => array_search($name, config('appearance.groups'), true));

        return view('livewire.account.appearance', [
            'r' => $r,
            'groups' => $groups,
            'accents' => collect(config('appearance.accents'))->filter(fn ($a, $k) => in_array($k, $platform['accents'], true)),
            'defaultSkin' => $platform['skin'], 'defaultAccent' => $platform['accent'],
            'allowCustom' => $platform['allow_custom'],
            'derived' => $r['accent'] === 'custom' && $r['accent_hex'] ? AccentDeriver::derive($r['accent_hex'], 'dark') + ['light' => AccentDeriver::derive($r['accent_hex'], 'light')] : null,
            'back' => $this->backRoute(),
            'dials' => config('appearance.dials'),
            'modes' => ['light' => __('appearance.mode.light'), 'dark' => __('appearance.mode.dark'), 'system' => __('appearance.mode.system')],
            'currentMode' => $r['mode'] ?? 'dark',
        ]);
    }
}
