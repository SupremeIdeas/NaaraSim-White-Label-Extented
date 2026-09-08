<?php

namespace App\Livewire\Admin;

use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use App\Services\Partners\PartnerEarningsService;
use App\Services\Partners\PartnerService;
use App\Support\Auditor;
use App\Support\PartnerSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Partners (profit-sharing program). Enable the program, set global
 * defaults, promote a user to a partner, and set each partner's admin-owned,
 * confidential profit-share %, cadence and payout mode. super_admin & admin.
 * Re-authorized on every request (booted).
 */
#[Layout('components.layouts.admin')]
class Partners extends Component
{
    use WithPagination;

    // Global settings.
    public bool $enabled = false;

    public string $defaultCadence = 'monthly';

    public string $defaultMode = 'manual';

    // Add-a-partner form.
    public string $newEmail = '';

    public $newShare = 5;

    // Per-partner edit buffer.
    public ?int $editingId = null;

    public $editShare = 0;

    public string $editCadence = 'monthly';

    public string $editMode = 'manual';

    /** Optional name for a brand-new partner account created here. */
    public string $newName = '';

    /** A generated temp password, shown to the admin once after creating an account. */
    public ?string $newTempPassword = null;

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->enabled = PartnerSettings::enabled();
        $this->defaultCadence = PartnerSettings::defaultCadence();
        $this->defaultMode = PartnerSettings::defaultMode();
    }

    public function saveSettings(): void
    {
        $this->validate([
            'defaultCadence' => 'required|in:weekly,monthly',
            'defaultMode' => 'required|in:manual,auto',
        ]);
        Setting::setValue(PartnerSettings::FLAG, $this->enabled, 'partners');
        Setting::setValue(PartnerSettings::DEFAULT_CADENCE, $this->defaultCadence, 'partners');
        Setting::setValue(PartnerSettings::DEFAULT_MODE, $this->defaultMode, 'partners');
        Auditor::log('partners.settings_updated', null, null, ['enabled' => $this->enabled]);
        $this->saved = 'Partner settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Partner settings saved.');
    }

    public function addPartner(PartnerService $service): void
    {
        $this->validate([
            'newEmail' => 'required|email',
            'newName' => 'nullable|string|max:120',
            'newShare' => 'required|numeric|min:0|max:100',
        ]);
        $this->newTempPassword = null;

        $email = trim($this->newEmail);
        $user = User::where('email', $email)->first();

        // BUILD-4 §4.4: admin can CREATE a new partner account directly (no
        // self-signup). Reuse the standard user creation; generate a temp
        // password the admin copies to the partner. First login lands on /partner.
        if ($user === null) {
            $temp = \Illuminate\Support\Str::password(12);
            $user = User::create([
                'name' => trim($this->newName) ?: \Illuminate\Support\Str::before($email, '@'),
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make($temp),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $this->newTempPassword = $temp;
            \App\Support\Auditor::log('partner.account_created', 'User', $user->id, ['by' => Auth::id()]);
        }

        $service->create($user, (float) $this->newShare);
        \App\Support\Auditor::log('partner.added', 'User', $user->id, ['by' => Auth::id(), 'share' => (float) $this->newShare]);
        $this->reset('newEmail', 'newName', 'newShare');
        $this->newShare = 5;
        $this->dispatch('nx-toast', type: 'success',
            message: $this->newTempPassword ? 'Partner account created — copy the temporary password.' : 'Partner added.');
    }

    public function edit(int $id): void
    {
        $p = Partner::findOrFail($id);
        $this->editingId = $p->id;
        $this->editShare = (float) $p->profit_share_pct;
        $this->editCadence = $p->payout_cadence;
        $this->editMode = $p->payout_mode;
    }

    public function saveTerms(PartnerService $service): void
    {
        $this->validate([
            'editShare' => 'required|numeric|min:0|max:100',
            'editCadence' => 'required|in:weekly,monthly',
            'editMode' => 'required|in:manual,auto',
        ]);
        $service->updateTerms(Partner::findOrFail($this->editingId), (float) $this->editShare, $this->editCadence, $this->editMode);
        $this->reset('editingId');
        $this->dispatch('nx-toast', type: 'success', message: 'Partner terms saved.');
    }

    public function activate(int $id, PartnerService $service): void
    {
        $service->activate(Partner::findOrFail($id));
        $this->dispatch('nx-toast', type: 'success', message: 'Partner activated.');
    }

    public function suspend(int $id, PartnerService $service): void
    {
        $service->suspend(Partner::findOrFail($id));
        $this->dispatch('nx-toast', type: 'success', message: 'Partner suspended.');
    }

    public function remove(int $id, PartnerService $service): void
    {
        $service->remove(Partner::findOrFail($id));
        $this->dispatch('nx-toast', type: 'success', message: 'Partner removed.');
    }

    public function render()
    {
        $partners = Partner::with('owner')->latest('id')->paginate(15);
        $earnings = app(PartnerEarningsService::class);
        // Attach the confidential figures for the ADMIN view only.
        $partners->getCollection()->transform(function (Partner $p) use ($earnings) {
            $p->admin_balance = $earnings->balance($p);

            return $p;
        });

        return view('livewire.admin.partners', ['partners' => $partners]);
    }
}
