<?php

namespace App\Livewire\Admin;

use App\Jobs\ApplyUpdateJob;
use App\Models\PlatformUpdateAttempt;
use App\Models\Setting;
use App\Services\Updater\UpdateApplier;
use App\Services\Updater\PackageVerifier;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Platform Updater (Batch 2 §4). The single most sensitive screen in the
 * admin panel: upload a signed `.naaraupdate`, see its verified manifest BEFORE
 * applying (a human-confirmation moment with real information, not a blind-trust
 * click), then apply it as a queued job that self-rolls-back on any failure.
 *
 * super_admin only — one notch tighter than the usual admin gate, because this
 * writes code + runs migrations on the live app and its rollback path restores
 * the database.
 */
#[Layout('components.layouts.admin')]
class Updater extends Component
{
    use WithFileUploads;

    public $package;

    /** Verified manifest summary shown before the Apply button appears. */
    public ?array $verified = null;

    public ?string $verifyError = null;

    public ?string $status = null;

    /** Absolute path to the staged (verified) package awaiting apply. */
    public ?string $stagedPath = null;

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

    public function render()
    {
        return view('livewire.admin.updater');
    }
}
