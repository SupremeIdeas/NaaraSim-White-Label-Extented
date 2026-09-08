<?php

namespace App\Livewire;

use App\Models\StatusSubscriber;
use App\Support\StatusPage as Status;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public, unauthenticated status page (Section Builder §1). Serves both admin
 * oversight and external developers evaluating the API — hence no login gate.
 * Component health is derived live from ProviderStatus; suppliers are never
 * named (fronted by branded service groups).
 */
#[Layout('components.layouts.marketing')]
class StatusPage extends Component
{
    public string $email = '';

    public bool $subscribed = false;

    public function subscribe(): void
    {
        $data = $this->validate(['email' => 'required|email|max:190']);

        StatusSubscriber::updateOrCreate(
            ['email' => strtolower(trim($data['email']))],
            ['is_active' => true],
        );

        $this->subscribed = true;
        $this->email = '';
        $this->dispatch('nx-toast', type: 'success', message: "You'll get status updates by email.");
    }

    public function render()
    {
        return view('livewire.status-page', [
            'components' => Status::components(),
            'overall' => Status::overall(),
            'overallLabel' => Status::overallLabel(),
            'timeline' => Status::timeline(),
        ]);
    }
}
