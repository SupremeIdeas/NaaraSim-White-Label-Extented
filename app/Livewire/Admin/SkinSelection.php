<?php

namespace App\Livewire\Admin;

use App\Support\Appearance\LicensedSkins;
use App\Support\FeatureEntitlements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Your skins" — where a white-label licensee chooses which skins their licence fills (Prompt 22). The allowance itself comes from the
 * original platform; this screen only lets the licensee pick up to that many and say which is the default. White-label builds only: on
 * the master platform the route does not exist (404).
 */
#[Layout('components.layouts.admin')]
class SkinSelection extends Component
{
    /** Ordered picks: the first is the default. */
    public array $picked = [];

    public string $group = 'all';

    public string $search = '';

    public bool $confirming = false;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->picked = LicensedSkins::chosen();
    }

    private function authorizeAdmin(): void
    {
        abort_if(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function toggle(string $key): void
    {
        $this->authorizeAdmin();
        $this->reset(['message', 'error', 'confirming']);
        if (! LicensedSkins::activated() || ! in_array($key, LicensedSkins::catalogue(), true)) {
            return;
        }
        if (in_array($key, $this->picked, true)) {
            $this->picked = array_values(array_diff($this->picked, [$key]));

            return;
        }
        if (count($this->picked) >= LicensedSkins::allowance()) {
            $this->error = 'Your licence unlocks '.LicensedSkins::allowance().' skin(s). Untick one first.';

            return;
        }
        $this->picked[] = $key;
    }

    public function makeDefault(string $key): void
    {
        $this->authorizeAdmin();
        $this->reset(['message', 'error', 'confirming']);
        if (in_array($key, $this->picked, true)) {
            $this->picked = array_values(array_merge([$key], array_diff($this->picked, [$key])));
        }
    }

    public function review(): void
    {
        $this->authorizeAdmin();
        $this->reset(['message', 'error']);
        $check = LicensedSkins::validateSelection($this->picked);
        if (! $check['ok']) {
            $this->error = implode(' ', $check['errors']);

            return;
        }
        $this->confirming = true;
    }

    public function cancelReview(): void
    {
        $this->confirming = false;
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $this->reset(['message', 'error']);
        $key = 'skin-selection:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Too many changes. Please wait a minute.';
            $this->confirming = false;

            return;
        }
        RateLimiter::hit($key, 60);

        $result = LicensedSkins::saveSelection($this->picked, Auth::id());
        $this->confirming = false;
        if (! $result['ok']) {
            $this->error = implode(' ', $result['errors']);

            return;
        }
        $this->picked = LicensedSkins::chosen();
        $this->message = 'Saved. Your members now see exactly these skins.';
        $this->dispatch('nx-toast', type: 'success', message: $this->message);
    }

    public function render()
    {
        $this->authorizeAdmin();
        $skins = collect(config('appearance.skins'))->filter(fn ($s, $k) => in_array($k, LicensedSkins::catalogue(), true));
        if ($this->group !== 'all') {
            $skins = $skins->filter(fn ($s) => $s['group'] === $this->group);
        }
        $q = mb_strtolower(trim($this->search));
        if ($q !== '') {
            $skins = $skins->filter(fn ($s, $k) => str_contains(mb_strtolower($s['label'].' '.$s['blurb'].' '.$s['group']), $q));
        }
        $groups = $skins->groupBy('group', true)->sortBy(fn ($g, $name) => array_search($name, config('appearance.groups'), true));
        $saved = LicensedSkins::chosen();

        return view('livewire.admin.skin-selection', [
            'activated' => LicensedSkins::activated(),
            'allowance' => LicensedSkins::allowance(),
            'saved' => $saved,
            'groups' => $groups,
            'allGroups' => config('appearance.groups'),
            'hiddenCount' => max(0, count(LicensedSkins::catalogue()) - count($this->picked)),
            'canAddMore' => max(0, LicensedSkins::allowance() - count($saved)),
            'overAllowance' => count((array) \App\Models\Setting::getValue(LicensedSkins::SELECTED_KEY, [])) > LicensedSkins::allowance(),
        ]);
    }
}
