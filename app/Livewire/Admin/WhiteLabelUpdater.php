<?php

namespace App\Livewire\Admin;

use App\Jobs\ApplyUpdateJob;
use App\Models\PlatformUpdateAttempt;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Services\Updater\PackageVerifier;
use App\Services\Updater\ThemeInstaller;
use App\Services\Updater\UpdateApplier;
use App\Services\Updater\WhiteLabelUpdateClient;
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
 * Admin → Platform Updater — WHITE-LABEL SUBSCRIBER SIDE (Batch 5 §3). Fork-only:
 * the original platform uses Admin\Updater (upload-only, since it's the
 * publisher); this instance is a subscriber, so it gets a primary "pull from
 * the original platform" flow on top of the same manual-upload fallback —
 * genuinely different UIs over the identical, unmodified apply engine from
 * Batches 2-3 (UpdateApplier / ThemeInstaller / PackageVerifier).
 *
 * Two entry points per tab, both ending at the SAME apply pipeline:
 *   - Pull: "Check for updates" -> WhiteLabelUpdateClient::checkForUpdates()
 *     -> pick one -> download() (verifies locally before returning) -> hand
 *     the path to the unmodified UpdateApplier/ThemeInstaller, exactly like
 *     the manual flow. Code applies auto-report their outcome back to the
 *     original platform via ApplyUpdateJob (Batch 5 §4); the theme flow
 *     reports inline since it's synchronous.
 *   - Manual upload: kept present, unchanged from Batch 2/3 — if this
 *     instance can't reach the original platform (firewall, connectivity, or
 *     NAARA_UPDATE_API_TOKEN simply not set up yet), an admin can still apply
 *     a `.naaraupdate` file emailed to them directly. This must never
 *     silently depend on the pull path being healthy (Batch 5 §6).
 *
 * Same strict super_admin gate as the original platform's screen — rollback
 * restores the database; this is still the single most sensitive screen.
 */
#[Layout('components.layouts.admin')]
class WhiteLabelUpdater extends Component
{
    use WithFileUploads;

    /** Which tab is open: 'code' or 'themes'. */
    public string $tab = 'code';

    // --- Pull flow (code) ---

    /** Result of the last checkForUpdates() call: ['ok'=>bool,'packages'=>[],'error'=>?string]. */
    public ?array $availableUpdates = null;

    public ?string $pullStatus = null;

    // --- Feature entitlement (Batch 8B) ---

    /** Result of the last refreshEntitlement() call: ['ok','level','locks','error']. */
    public ?array $entitlement = null;

    public ?string $entitlementStatus = null;

    // --- Manual upload (code) — unchanged from Batch 2 ---

    public $package;

    public ?array $verified = null;

    public ?string $verifyError = null;

    public ?string $status = null;

    public ?string $stagedPath = null;

    // --- Pull flow (themes) ---

    public ?array $availableThemes = null;

    // --- Manual upload (themes) — unchanged from Batch 3 ---

    public $themePackage;

    public ?array $themePreview = null;

    public ?string $themeVerifyError = null;

    public ?string $themeStatus = null;

    public ?string $themeStagedPath = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
    }

    // ==================== CODE — pull flow ====================

    public function checkForCodeUpdates(WhiteLabelUpdateClient $client): void
    {
        $this->pullStatus = null;
        $this->availableUpdates = $client->checkForUpdates('code');
    }

    /**
     * Download (verifies locally) then hand off to the SAME queued apply
     * pipeline the manual flow uses — identical downstream behaviour
     * (maintenance mode, backup, health-check gate, auto-rollback). The apply
     * job reports its own outcome back to the original platform once it
     * finishes (Batch 5 §4), so nothing further is needed here.
     */
    public function pullAndApply(string $packageId, WhiteLabelUpdateClient $client): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        try {
            $path = $client->download($packageId, 'code');
        } catch (\Throwable $e) {
            $this->pullStatus = 'Download failed: '.$e->getMessage();

            return;
        }

        ApplyUpdateJob::dispatch($path, Auth::id());
        Auditor::log('update.apply_requested', null, null, ['version' => $packageId, 'source' => 'pull']);

        $this->availableUpdates = null;
        $this->pullStatus = 'Downloaded and queued. It runs in the background with a full backup and automatic rollback — watch the status below.';
    }

    // ==================== CODE — manual upload (unchanged from Batch 2) ====================

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
        Auditor::log('update.apply_requested', null, null, ['version' => $this->verified['version'] ?? null, 'source' => 'manual']);

        $this->reset(['package', 'verified', 'stagedPath']);
        $this->status = 'Update queued. It runs in the background with a full backup and automatic rollback — watch the status below.';
    }

    // ==================== FEATURE ENTITLEMENT (Batch 8B) ====================

    /**
     * Manually re-pull this instance's feature entitlement from the original
     * platform. It also refreshes automatically on every code check-in; this
     * button is for the operator who just paid (or was just upgraded) and wants
     * their locks to lift right now without waiting for the next update poll.
     */
    public function refreshEntitlement(WhiteLabelUpdateClient $client): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $this->entitlement = $client->refreshEntitlement();
        $this->entitlementStatus = $this->entitlement['ok']
            ? 'Entitlement refreshed from the original platform.'
            : ('Could not refresh entitlement: '.($this->entitlement['error'] ?? 'unknown error'));
    }

    /**
     * The lock list currently cached on THIS instance (what the gates actually
     * enforce right now), each mapped to its human label. Read straight from the
     * universal resolver so it reflects reality, not just the last fetch result.
     *
     * @return array<int,array{key:string,label:string}>
     */
    public function getActiveLocksProperty(): array
    {
        $catalog = \App\Support\FeatureLocks::catalog();

        return array_map(
            fn (string $key) => ['key' => $key, 'label' => $catalog[$key] ?? $key],
            \App\Support\FeatureEntitlements::all(),
        );
    }

    public function getCurrentVersionProperty(): ?string
    {
        return Setting::getValue(UpdateApplier::VERSION_SETTING);
    }

    public function getAttemptsProperty()
    {
        return PlatformUpdateAttempt::query()->latest()->limit(15)->get();
    }

    // ==================== THEMES — pull flow ====================

    public function checkForThemeUpdates(WhiteLabelUpdateClient $client): void
    {
        $this->themeStatus = null;
        $this->availableThemes = $client->checkForUpdates('theme');
    }

    /**
     * Download (verifies locally), install synchronously via the unmodified
     * ThemeInstaller, then report the outcome inline — themes install
     * same-request (no queue), so there's no async job to report from.
     */
    public function pullAndInstallTheme(string $packageId, WhiteLabelUpdateClient $client, ThemeInstaller $installer): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        try {
            $path = $client->download($packageId, 'theme');
        } catch (\Throwable $e) {
            $this->themeStatus = 'Download failed: '.$e->getMessage();

            return;
        }

        try {
            $row = $installer->install($path, Auth::id());
        } catch (\Throwable $e) {
            $client->reportOutcome($packageId, 'failed', ['notes' => $e->getMessage()]);
            $this->themeStatus = 'Install failed: '.$e->getMessage();

            return;
        } finally {
            @unlink($path);
        }

        $client->reportOutcome($packageId, 'applied', ['applied_at' => now()->toIso8601String()]);
        $this->availableThemes = null;
        $this->themeStatus = "{$row->name} downloaded and installed.";
    }

    // ==================== THEMES — manual upload (unchanged from Batch 3) ====================

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
        return view('livewire.admin.white-label-updater');
    }
}
