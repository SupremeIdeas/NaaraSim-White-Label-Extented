<?php

namespace App\Livewire\Admin;

use App\Models\NavSlot;
use App\Support\Auditor;
use App\Support\NavSlots as NavSlotsSupport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Floating nav. Reassign any slot of the floating navigation pill to any
 * page/feature, choose who sees it (all / signed-in / signed-out), mark the
 * glowing centerpiece, reorder, and toggle. Repointing a slot is a config change,
 * not a code edit. Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class NavSlots extends Component
{
    /** slots[id] => editable fields. */
    public array $slots = [];

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        NavSlotsSupport::ensureSeeded();
        $this->load();
    }

    private function load(): void
    {
        $this->slots = NavSlot::orderBy('position')->orderBy('id')->get()
            ->mapWithKeys(fn (NavSlot $s) => [$s->id => [
                'label' => $s->label, 'icon' => $s->icon, 'target' => $s->target,
                'visibility' => $s->visibility, 'position' => $s->position,
                'is_center' => $s->is_center, 'is_active' => $s->is_active,
            ]])->all();
    }

    public function addSlot(): void
    {
        $slot = NavSlot::create([
            'label' => 'New', 'icon' => 'grid', 'target' => '/', 'visibility' => 'all',
            'position' => (int) NavSlot::max('position') + 1, 'is_active' => true,
        ]);
        NavSlotsSupport::flush();
        $this->load();
    }

    public function remove(int $id): void
    {
        NavSlot::whereKey($id)->delete();
        NavSlotsSupport::flush();
        unset($this->slots[$id]);
        $this->dispatch('nx-toast', type: 'success', message: 'Slot removed.');
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        foreach ($this->slots as $id => $data) {
            $slot = NavSlot::find($id);
            if (! $slot) {
                continue;
            }
            $slot->update([
                'label' => mb_substr(trim((string) ($data['label'] ?? 'Slot')), 0, 24) ?: 'Slot',
                'icon' => trim((string) ($data['icon'] ?? 'grid')) ?: 'grid',
                'target' => trim((string) ($data['target'] ?? '/')) ?: '/',
                'visibility' => in_array($data['visibility'] ?? 'all', NavSlot::VISIBILITIES, true) ? $data['visibility'] : 'all',
                'position' => (int) ($data['position'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? false),
                'is_center' => (bool) ($data['is_center'] ?? false),
            ]);
        }

        // Only one centerpiece — keep the lowest-id center, demote the rest.
        $centers = NavSlot::where('is_center', true)->orderBy('id')->get();
        $centers->skip(1)->each(fn (NavSlot $s) => $s->update(['is_center' => false]));

        NavSlotsSupport::flush();
        $this->load();
        Auditor::log('nav.slots_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Floating nav saved.');
    }

    public function resetDefaults(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        NavSlot::query()->delete();
        NavSlotsSupport::flush();
        NavSlotsSupport::ensureSeeded();
        $this->load();
        $this->dispatch('nx-toast', type: 'success', message: 'Reset to defaults.');
    }

    public function render()
    {
        return view('livewire.admin.nav-slots');
    }
}
