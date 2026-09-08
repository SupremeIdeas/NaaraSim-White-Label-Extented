<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The global login-notice pop-up (customer shell). On mount it asks AlertService
 * for the one notice this user should see, records the view, and renders a
 * dismissable modal. Cancel marks it dismissed (never shown again); the CTA is
 * an admin-set link. Renders nothing when there's no eligible notice.
 */
class AlertPopup extends Component
{
    public ?int $alertId = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if ($alert = app(AlertService::class)->nextFor($user)) {
            $this->alertId = $alert->id;
            app(AlertService::class)->recordView($alert, $user);
        }
    }

    public function dismiss(): void
    {
        if ($this->alertId && ($user = Auth::user())) {
            if ($alert = Alert::find($this->alertId)) {
                app(AlertService::class)->dismiss($alert, $user);
            }
        }
        $this->alertId = null;
    }

    public function render()
    {
        return view('livewire.alert-popup', [
            'alert' => $this->alertId ? Alert::find($this->alertId) : null,
        ]);
    }
}
