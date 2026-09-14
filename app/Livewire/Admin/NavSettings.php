<?php

namespace App\Livewire\Admin;

use App\Models\NavItemOverride;
use App\Support\Auditor;
use App\Support\BottomNav;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Bottom nav (owner request). Reorder or replace which items appear
 * in the main bottom bar / More sheet, and in the Numbers section's own
 * bottom bar — a drag list per nav, first 4 active rows = the visible bar,
 * the rest overflow to "More" (Numbers is always exactly 4 slots). Only
 * reorders/hides items App\Support\BottomNav's eligibility rules already
 * allow for a given viewer — never a way to expose an unauthorized link.
 * Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class NavSettings extends Component
{
    /** @var list<string> */
    public array $mainOrder = [];

    /** @var list<string> */
    public array $mainHidden = [];

    /** @var list<string> */
    public array $numbersOrder = [];

    /** @var list<string> */
    public array $numbersHidden = [];

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->mainOrder = $this->orderedKeys('main', array_keys(BottomNav::MAIN_CATALOG));
        $this->mainHidden = NavItemOverride::where('nav', 'main')->where('is_active', false)->pluck('item_key')->all();
        $this->numbersOrder = $this->orderedKeys('numbers', array_keys(BottomNav::NUMBERS_CATALOG));
        $this->numbersHidden = NavItemOverride::where('nav', 'numbers')->where('is_active', false)->pluck('item_key')->all();
    }

    /** Stored order first, then any catalog keys not yet configured. */
    private function orderedKeys(string $nav, array $catalogKeys): array
    {
        $stored = NavItemOverride::where('nav', $nav)->orderBy('position')->pluck('item_key')->all();
        $remaining = array_values(array_diff($catalogKeys, $stored));

        return array_values(array_intersect(array_merge($stored, $remaining), $catalogKeys));
    }

    public function reorderMain(array $keys): void
    {
        $this->persist('main', $keys, $this->mainHidden);
    }

    public function reorderNumbers(array $keys): void
    {
        $this->persist('numbers', $keys, $this->numbersHidden);
    }

    public function toggleMain(string $key): void
    {
        $hidden = in_array($key, $this->mainHidden, true)
            ? array_values(array_diff($this->mainHidden, [$key]))
            : array_merge($this->mainHidden, [$key]);
        $this->persist('main', $this->mainOrder, $hidden);
    }

    public function toggleNumbers(string $key): void
    {
        $hidden = in_array($key, $this->numbersHidden, true)
            ? array_values(array_diff($this->numbersHidden, [$key]))
            : array_merge($this->numbersHidden, [$key]);
        $this->persist('numbers', $this->numbersOrder, $hidden);
    }

    private function persist(string $nav, array $order, array $hidden): void
    {
        foreach (array_values($order) as $i => $key) {
            NavItemOverride::updateOrCreate(
                ['nav' => $nav, 'item_key' => $key],
                ['position' => $i, 'is_active' => ! in_array($key, $hidden, true)]
            );
        }
        $this->load();
        Auditor::log('nav.bottom_nav_saved', payload: ['nav' => $nav]);
        $this->dispatch('nx-toast', type: 'success', message: 'Bottom nav saved.');
    }

    public function resetMain(): void
    {
        NavItemOverride::where('nav', 'main')->delete();
        $this->load();
        $this->dispatch('nx-toast', type: 'success', message: 'Reset to platform defaults.');
    }

    public function resetNumbers(): void
    {
        NavItemOverride::where('nav', 'numbers')->delete();
        $this->load();
        $this->dispatch('nx-toast', type: 'success', message: 'Reset to platform defaults.');
    }

    public function render()
    {
        return view('livewire.admin.nav-settings', [
            'mainCatalog' => BottomNav::MAIN_CATALOG,
            'numbersCatalog' => BottomNav::NUMBERS_CATALOG,
        ]);
    }
}
