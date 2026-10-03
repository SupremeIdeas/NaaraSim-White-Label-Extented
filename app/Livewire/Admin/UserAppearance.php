<?php

namespace App\Livewire\Admin;

use App\Models\AppearancePreset;
use App\Models\Setting;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin -> User appearance (Prompt 20 §19): what members may choose for THEIR dashboards. The platform default skin and
 * accent, which presets exist, whether custom colours are allowed and the operator lock. Presentation only: never touches
 * providers, pricing, wallet or orders. (Route is `admin.user-appearance`; `admin.appearance` is the Splash page.)
 */
#[Layout('components.layouts.admin')]
class UserAppearance extends Component
{
    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    private function preset(string $kind, string $key): ?AppearancePreset
    {
        return AppearancePreset::where('kind', $kind)->where('key', $key)->first();
    }

    private function built(string $skin): bool
    {
        return in_array($skin, (array) config('appearance.built'), true);
    }

    /** Skins come from the licence on a white-label build; the licensee picks them on the Your skins screen, not here. */
    private function skinsManagedByLicence(): bool
    {
        if (! \App\Support\Appearance\LicensedSkins::enforced()) {
            return false;
        }
        $this->error = 'Your skins are set by your licence. Choose them on the Your skins screen.';

        return true;
    }

    public function setDefaultSkin(string $key): void
    {
        $this->authorizeAdmin();
        $this->reset(['message', 'error']);
        if ($this->skinsManagedByLicence()) {
            return;
        }
        $row = $this->preset(AppearancePreset::SKIN, $key);
        if ($row === null || ! $this->built($key)) {
            $this->error = 'That skin is not available yet.';

            return;
        }
        $this->makeDefault($row);
    }

    public function setDefaultAccent(string $key): void
    {
        $this->authorizeAdmin();
        $this->reset(['message', 'error']);
        $row = $this->preset(AppearancePreset::ACCENT, $key);
        if ($row === null) {
            return;
        }
        $this->makeDefault($row);
    }

    /** The default must stay enabled, so choosing it enables it. Exactly one default per kind (transaction, DB-agnostic). */
    private function makeDefault(AppearancePreset $row): void
    {
        DB::transaction(function () use ($row) {
            AppearancePreset::where('kind', $row->kind)->where('is_default', true)->update(['is_default' => false]);
            $row->forceFill(['is_default' => true, 'enabled' => true])->save();
        });
        $this->audit('default_'.$row->kind, ['key' => $row->key]);
        $this->message = 'Saved. Audit log written.';
    }

    public function toggle(string $kind, string $key): void
    {
        $this->authorizeAdmin();
        $this->reset(['message', 'error']);
        if ($kind === AppearancePreset::SKIN && $this->skinsManagedByLicence()) {
            return;
        }
        $row = $this->preset($kind, $key);
        if ($row === null) {
            return;
        }
        if ($row->is_default) {
            $this->error = $kind === AppearancePreset::SKIN ? 'The default skin must stay enabled.' : 'The default accent must stay enabled.';

            return;
        }
        if ($kind === AppearancePreset::SKIN && ! $row->enabled && ! $this->built($key)) {
            $this->error = 'That skin is not available yet.';

            return;
        }
        $row->forceFill(['enabled' => ! $row->enabled])->save();
        $this->audit("toggle_{$kind}", ['key' => $key, 'enabled' => $row->enabled]);
        $this->message = 'Saved. Audit log written.';
    }

    public function toggleLock(): void
    {
        $this->authorizeAdmin();
        $this->flip(AppearanceResolver::LOCK, false, 'lock');
    }

    public function toggleCustom(): void
    {
        $this->authorizeAdmin();
        $this->flip(AppearanceResolver::ALLOW_CUSTOM, true, 'custom_accent');
    }

    private function flip(string $setting, bool $default, string $what): void
    {
        $this->reset(['message', 'error']);
        $now = ! (bool) Setting::getValue($setting, $default);
        Setting::setValue($setting, $now, 'ui');
        $this->audit($what, ['value' => $now]);
        $this->message = 'Saved. Audit log written.';
    }

    private function audit(string $what, array $meta): void
    {
        AppearanceResolver::forgetPlatform();
        Auditor::log('appearance.admin_updated', 'AppearancePreset', null, ['change' => $what] + $meta + ['by' => Auth::id()]);
    }

    public function render()
    {
        $this->authorizeAdmin();
        $rows = AppearancePreset::query()->orderBy('sort')->get()->groupBy('kind');

        $licensed = \App\Support\Appearance\LicensedSkins::enforced();
        $skinRows = $rows->get(AppearancePreset::SKIN, collect());
        if ($licensed) {
            // A hidden skin is not even listed: the licence decides what exists for this install's members.
            $skinRows = $skinRows->filter(fn ($r) => in_array($r->key, \App\Support\Appearance\LicensedSkins::available(), true))->values();
        }

        return view('livewire.admin.user-appearance', [
            'licensed' => $licensed,
            'skinsRoute' => $licensed && \Illuminate\Support\Facades\Route::has('admin.skins') ? route('admin.skins', ['adminGateway' => request()->route('adminGateway')]) : null,
            'skins' => $skinRows,
            'accents' => $rows->get(AppearancePreset::ACCENT, collect()),
            'catalog' => config('appearance.skins'),
            'built' => (array) config('appearance.built'),
            'locked' => (bool) Setting::getValue(AppearanceResolver::LOCK, false),
            'custom' => (bool) Setting::getValue(AppearanceResolver::ALLOW_CUSTOM, true),
            'myAppearance' => route('admin.my-appearance', ['adminGateway' => request()->route('adminGateway')]),
        ]);
    }
}
