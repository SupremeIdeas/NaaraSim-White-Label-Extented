<?php

namespace App\Livewire\Admin\Nci;

use App\Services\Routing\CandidateOrdering;
use App\Support\OperationsCenter;
use App\Support\ProviderModels;
use App\Support\RoutingPreference;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NAARA-BUILD-17 §1.4 — Routing Console. A READ-ONLY simulation of the order the
 * real router would try per product family — it calls the SAME CandidateOrdering
 * the routers use, without executing a purchase. Plus the manual preference
 * affordance ("prefer Provider X for the next N hours") which writes the single
 * admin-priority flag the ordering already honours (RoutingPreference), not a
 * second override mechanism.
 */
#[Layout('components.layouts.admin')]
class RoutingConsole extends Component
{
    /** family key => stack for the per-family simulation. */
    private const FAMILY_STACK = [
        'naara_data' => 'esim', 'naara_connect' => 'esim',
        'naara_verify' => 'sms', 'naara_rent' => 'sms', 'naara_line' => 'permanent',
    ];

    public string $prefStack = 'esim';

    public string $prefProvider = '';

    public int $prefHours = 4;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.view'), 403);
    }

    private function assertOverride(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.override'), 403);
    }

    public function setPreference(): void
    {
        $this->assertOverride();
        $this->validate([
            'prefStack' => 'required|in:esim,sms,permanent',
            'prefProvider' => 'required|string',
            'prefHours' => 'required|integer|min:1|max:168',
        ]);
        RoutingPreference::prefer($this->prefStack, $this->prefProvider, $this->prefHours);
        \App\Support\Auditor::log('nci.routing_preference_set', null, null, ['stack' => $this->prefStack, 'provider' => $this->prefProvider, 'hours' => $this->prefHours]);
        \App\Models\ProviderRegistry::flushSnapshot();
        $this->dispatch('nx-toast', type: 'success', message: 'Preference set — '.$this->prefProvider.' is preferred for '.$this->prefHours.'h.');
    }

    public function clearPreference(string $stack): void
    {
        $this->assertOverride();
        RoutingPreference::clear($stack);
        \App\Support\Auditor::log('nci.routing_preference_cleared', null, null, ['stack' => $stack]);
        $this->dispatch('nx-toast', type: 'success', message: 'Preference cleared for '.$stack.'.');
    }

    /**
     * BUILD-19 §1 — the NCI kill switch. Flips nci.enabled; when OFF, ordering
     * ignores nci_score entirely and falls back to latency / success-rate (circuit
     * breakers stay fully active either way, see CandidateOrdering). Every toggle
     * is audited with who + when so the on/off history is never a mystery.
     */
    public function toggleNci(): void
    {
        $this->assertOverride();
        $now = (bool) \App\Models\Setting::getValue('nci.enabled', true);
        $next = ! $now;
        \App\Models\Setting::setValue('nci.enabled', $next);
        \App\Models\ProviderRegistry::flushSnapshot();
        \App\Support\Auditor::log('nci.enabled_toggled', null, null, ['enabled' => $next]);
        $this->dispatch('nx-toast', type: 'success', message: 'NCI intelligence '.($next ? 'ENABLED' : 'DISABLED').'.');
    }

    public function render()
    {
        $ordering = app(CandidateOrdering::class);
        $families = [];
        foreach (self::FAMILY_STACK as $family => $stack) {
            $model = ProviderModels::find($family);
            if ($model === null) {
                continue;
            }
            $families[] = [
                'key' => $family,
                'name' => OperationsCenter::familyName($family),
                'stack' => $stack,
                'order' => $ordering->order($model['lane'] ?? [], $stack),
            ];
        }

        $prefs = [];
        foreach (['esim', 'sms', 'permanent'] as $stack) {
            $prefs[$stack] = RoutingPreference::current($stack);
        }

        return view('livewire.admin.nci.routing-console', [
            'families' => $families,
            'prefs' => $prefs,
            'nciEnabled' => (bool) \App\Models\Setting::getValue('nci.enabled', true),
        ]);
    }
}
