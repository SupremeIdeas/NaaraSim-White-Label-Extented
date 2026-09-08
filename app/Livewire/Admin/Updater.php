<?php

namespace App\Livewire\Admin;

use App\Jobs\ApplyUpdateJob;
use App\Models\PlatformUpdateAttempt;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Services\Updater\ThemeInstaller;
use App\Services\Updater\UpdateApplier;
use App\Services\Updater\PackageVerifier;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\ThemePreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Platform Updater (Batch 2 §4, Batch 3 §3). The single most sensitive
 * "install something onto the platform" screen in the admin panel, in two tabs:
 *
 * - Code updates: upload a signed `.naaraupdate`, see its verified manifest
 *   BEFORE applying (a human-confirmation moment with real information, not a
 *   blind-trust click), then apply it as a queued job that self-rolls-back on
 *   any failure.
 * - Themes: upload a signed theme `.naaraupdate` (package_type: "theme"), see
 *   its name/persona/colour swatches/font warnings before installing, then
 *   activate or remove installed (non-built-in) themes.
 *
 * Both tabs are gated super_admin only. Per Batch 3 §0, a theme install is
 * genuinely lower-risk than a code update (ThemePreset's render path only ever
 * emits a small whitelisted set of CSS variables, so even a malicious theme
 * can't inject CSS or execute code) — but this stays the one screen for
 * "install a package onto this platform," so it keeps the same strict gate as
 * the code-update tab rather than splitting access by action. The existing
 * day-to-day Admin\ThemePicker screen (activate/tweak an already-installed
 * theme) keeps its own broader admin/theme.manage gate unchanged — this tab is
 * specifically for installing a NEW theme package, which is the batch-like,
 * more consequential action.
 */
#[Layout('components.layouts.admin')]
class Updater extends Component
{
    use WithFileUploads;

    /** Which tab is open: 'code' or 'themes'. */
    public string $tab = 'code';

    public $package;

    /** Verified manifest summary shown before the Apply button appears. */
    public ?array $verified = null;

    public ?string $verifyError = null;

    public ?string $status = null;

    /** Absolute path to the staged (verified) package awaiting apply. */
    public ?string $stagedPath = null;

    // --- Themes tab state ---

    public $themePackage;

    /** Preview summary shown before the Install button appears. */
    public ?array $themePreview = null;

    public ?string $themeVerifyError = null;

    public ?string $themeStatus = null;

    /** Absolute path to the staged (previewed) theme package awaiting install. */
    public ?string $themeStagedPath = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
    }

    public function verifyPackage(PackageVerifier $verifier): void
    {
        $this->reset(['verified', 'verifyError', 'stagedPath', 'status']);

        $this->validate([
            'package' => 'required|file|max:1048576', // up to 1 GB
        ]);

        if (! str_ends_with(strtolower($this->package->getClientOriginalName()), '.naaraupdate')) {
            $this->verifyError = 'That is not a .naaraupdate file.';

            return;
        }

        // Move off the volatile livewire-tmp area to a stable local path.
        $relative = $this->package->storeAs('update-incoming', 'upload-'.Str::uuid().'.naaraupdate', 'local');
        $absolute = Storage::disk('local')->path($relative);

        $result = $verifier->verify($absolute);
        if (! $result->passed) {
            @unlink($absolute);
            $this->verifyError = $result->reason;
            Auditor::log('update.rejected', null, null, ['reason' => $result->reason, 'stage' => 'admin-upload']);

            return;
        }

        $m = $result->manifest;

        // A theme package went into the wrong tab — route it correctly rather
        // than letting it run the full maintenance-mode/backup/health-check
        // pipeline for something that doesn't need it.
        if ($m->packageType === 'theme') {
            @unlink($absolute);
            $this->verifyError = 'This is a theme package — install it from the Themes tab instead.';

            return;
        }

        $this->stagedPath = $absolute;
        $this->verified = [
            'product' => $m->product,
            'version' => $m->version,
            'min_compatible' => $m->minCompatibleVersion,
            'package_type' => $m->packageType,
            'files' => count($m->files),
            'migrations' => count($m->migrations),
            'deletions' => count($m->deletions),
            'requires_composer' => $m->requiresComposerInstall,
            'requires_npm' => $m->requiresNpmBuild,
            'changelog' => $m->changelog,
        ];
    }

    public function applyPackage(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        if ($this->stagedPath === null || ! is_file($this->stagedPath)) {
            $this->verifyError = 'No verified package is staged — upload and verify one first.';

            return;
        }

        ApplyUpdateJob::dispatch($this->stagedPath, Auth::id());
        Auditor::log('update.apply_requested', null, null, ['version' => $this->verified['version'] ?? null]);

        $this->reset(['package', 'verified', 'stagedPath']);
        $this->status = 'Update queued. It runs in the background with a full backup and automatic rollback — watch the status below.';
    }

    public function getCurrentVersionProperty(): ?string
    {
        return Setting::getValue(UpdateApplier::VERSION_SETTING);
    }

    public function getAttemptsProperty()
    {
        return PlatformUpdateAttempt::query()
            ->latest()
            ->limit(15)
            ->get();
    }

    // --- Themes tab ---

    public function verifyTheme(ThemeInstaller $installer): void
    {
        $this->reset(['themePreview', 'themeVerifyError', 'themeStagedPath', 'themeStatus']);

        $this->validate([
            'themePackage' => 'required|file|max:1048576',
        ]);

        if (! str_ends_with(strtolower($this->themePackage->getClientOriginalName()), '.naaraupdate')) {
            $this->themeVerifyError = 'That is not a .naaraupdate file.';

            return;
        }

        $relative = $this->themePackage->storeAs('update-incoming', 'theme-'.Str::uuid().'.naaraupdate', 'local');
        $absolute = Storage::disk('local')->path($relative);

        try {
            $preview = $installer->preview($absolute);
        } catch (\Throwable $e) {
            @unlink($absolute);
            $this->themeVerifyError = $e->getMessage();
            Auditor::log('update.rejected', null, null, ['reason' => $e->getMessage(), 'stage' => 'admin-theme-upload']);

            return;
        }

        $this->themeStagedPath = $absolute;
        $this->themePreview = [
            'slug' => $preview->slug,
            'name' => $preview->name,
            'persona' => $preview->persona,
            'swatches' => $preview->colorSwatches,
            'icon_family' => $preview->iconFamily,
            'layout_variants' => $preview->layoutVariants,
            'font_warnings' => $preview->fontWarnings,
            'version' => $preview->version,
        ];
    }

    public function installTheme(ThemeInstaller $installer): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        if ($this->themeStagedPath === null || ! is_file($this->themeStagedPath)) {
            $this->themeVerifyError = 'No verified theme is staged — upload and verify one first.';

            return;
        }

        try {
            $row = $installer->install($this->themeStagedPath, Auth::id());
        } catch (\Throwable $e) {
            $this->themeVerifyError = $e->getMessage();

            return;
        } finally {
            @unlink($this->themeStagedPath);
        }

        $warnings = $this->themePreview['font_warnings'] ?? [];
        $this->reset(['themePackage', 'themePreview', 'themeStagedPath']);
        $this->themeStatus = "{$row->name} installed."
            .($warnings === [] ? '' : ' '.count($warnings).' font warning(s) — see the theme picker for details.');
    }

    /** Activate an installed theme platform-wide — same primitives ThemePicker::apply() uses. */
    public function activateTheme(string $slug): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $exists = ThemePresetModel::where('slug', $slug)->exists();
        if (! $exists) {
            $this->themeVerifyError = 'Unknown theme.';

            return;
        }

        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();
        Auditor::log('theme.applied', 'ThemePreset', null, ['slug' => $slug]);

        $this->themeStatus = 'Theme activated.';
    }

    /** Remove an installed, non-built-in theme and its stored assets. */
    public function removeTheme(string $slug): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            return;
        }
        if ($row->is_built_in) {
            $this->themeVerifyError = 'Built-in themes cannot be removed.';

            return;
        }

        // Never leave the active-theme setting pointing at a deleted row.
        if (Setting::getValue(ThemePreset::SETTING_KEY) === $slug) {
            Setting::setValue(ThemePreset::SETTING_KEY, ThemePreset::DEFAULT_SLUG);
        }

        Storage::disk(MediaStorage::disk())->deleteDirectory("themes/{$slug}");
        $row->delete();

        ThemePreset::bust();
        Auditor::log('theme.removed', 'ThemePreset', null, ['slug' => $slug]);
        $this->themeStatus = 'Theme removed.';
    }

    public function getInstalledThemesProperty()
    {
        return ThemePreset::all();
    }

    public function getActiveThemeSlugProperty(): string
    {
        return ThemePreset::slug();
    }

    public function render()
    {
        return view('livewire.admin.updater');
    }
}
