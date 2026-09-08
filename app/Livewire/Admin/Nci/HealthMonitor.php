<?php

namespace App\Livewire\Admin\Nci;

use App\Models\ProviderRegistry;
use App\Support\SchedulerHealth;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NAARA-BUILD-17 §1.3 — Health Monitor. A green/amber/red grid per provider that
 * shares the SAME data source as the System Health panel: the provider status is
 * the registry's live-truth (written by ProviderHealth), and the "is the
 * health-check cron actually firing / overdue" answer comes from SchedulerHealth,
 * so the two pages never disagree.
 */
#[Layout('components.layouts.admin')]
class HealthMonitor extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.view'), 403);
    }

    public function render()
    {
        $task = collect(SchedulerHealth::report())->firstWhere('name', 'providers:health-check');

        return view('livewire.admin.nci.health-monitor', [
            'rows' => ProviderRegistry::orderBy('stack')->orderBy('provider_key')->get(),
            'task' => $task,
        ]);
    }
}
