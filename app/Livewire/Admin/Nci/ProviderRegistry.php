<?php

namespace App\Livewire\Admin\Nci;

use App\Support\OperationsCenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * NAARA-BUILD-17 §1.1 — the Operations Center hub: the provider registry grouped
 * by Naara product family, tier-sorted within each, scannable status at a glance,
 * a one-click "Open dashboard" per row, and filters. Read-only view over Layer 1
 * data (OperationsCenter shapes it) — zero business logic here.
 */
#[Layout('components.layouts.admin')]
class ProviderRegistry extends Component
{
    #[Url]
    public string $stack = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $circuit = '';

    #[Url]
    public string $tier = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.view'), 403);
    }

    public function resetFilters(): void
    {
        $this->reset('stack', 'status', 'circuit', 'tier');
    }

    public function render()
    {
        $groups = OperationsCenter::groupedByFamily([
            'stack' => $this->stack, 'status' => $this->status,
            'circuit' => $this->circuit, 'tier' => $this->tier,
        ]);

        return view('livewire.admin.nci.provider-registry', ['groups' => $groups]);
    }
}
