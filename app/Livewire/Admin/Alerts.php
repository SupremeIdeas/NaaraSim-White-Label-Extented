<?php

namespace App\Livewire\Admin;

use App\Models\Alert;
use App\Support\Auditor;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Notices. Compose rich-text login pop-ups, target them (all / new /
 * returning), cap views, set a live window, attach a CTA link + coupon. Body is
 * allowlist-sanitized on save (XSS-safe). Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class Alerts extends Component
{
    public ?int $editingId = null;

    public array $form = [];

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->blank();
    }

    private function blank(): void
    {
        $this->editingId = null;
        $this->form = [
            'title' => '', 'body' => '', 'cta_label' => '', 'cta_url' => '', 'coupon_code' => '',
            'audience' => 'all', 'new_days' => 14, 'trigger' => 'any', 'max_views' => 1,
            'starts_at' => '', 'ends_at' => '', 'is_active' => true, 'priority' => 0,
        ];
    }

    public function edit(int $id): void
    {
        $a = Alert::findOrFail($id);
        $this->editingId = $a->id;
        $this->form = [
            'title' => $a->title, 'body' => $a->body, 'cta_label' => $a->cta_label,
            'cta_url' => $a->cta_url, 'coupon_code' => $a->coupon_code, 'audience' => $a->audience,
            'new_days' => $a->new_days, 'trigger' => $a->trigger, 'max_views' => $a->max_views,
            'starts_at' => $a->starts_at?->format('Y-m-d\TH:i'), 'ends_at' => $a->ends_at?->format('Y-m-d\TH:i'),
            'is_active' => $a->is_active, 'priority' => $a->priority,
        ];
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $data = $this->validate([
            'form.title' => 'required|string|max:120',
            'form.body' => 'nullable|string|max:6000',
            'form.cta_label' => 'nullable|string|max:40',
            'form.cta_url' => 'nullable|string|max:300',
            'form.coupon_code' => 'nullable|string|max:40',
            'form.audience' => 'required|in:'.implode(',', Alert::AUDIENCES),
            'form.new_days' => 'required|integer|min:1|max:365',
            'form.trigger' => 'required|in:'.implode(',', Alert::TRIGGERS),
            'form.max_views' => 'required|integer|min:0|max:100',
            'form.starts_at' => 'nullable|date',
            'form.ends_at' => 'nullable|date|after_or_equal:form.starts_at',
            'form.priority' => 'required|integer|min:0|max:1000',
        ])['form'];

        $data['body'] = HtmlSanitizer::clean($data['body'] ?? '');
        $data['is_active'] = (bool) ($this->form['is_active'] ?? true);
        $data['starts_at'] = $data['starts_at'] ?: null;
        $data['ends_at'] = $data['ends_at'] ?: null;

        $alert = Alert::updateOrCreate(['id' => $this->editingId], $data);
        Auditor::log($this->editingId ? 'alert.updated' : 'alert.created', Alert::class, $alert->id, ['title' => $alert->title]);

        $this->blank();
        $this->dispatch('nx-toast', type: 'success', message: 'Notice saved.');
    }

    public function newNotice(): void
    {
        $this->blank();
    }

    public function toggle(int $id): void
    {
        $a = Alert::findOrFail($id);
        $a->update(['is_active' => ! $a->is_active]);
    }

    public function delete(int $id): void
    {
        Alert::findOrFail($id)->delete();
        if ($this->editingId === $id) {
            $this->blank();
        }
        $this->dispatch('nx-toast', type: 'success', message: 'Notice deleted.');
    }

    public function render()
    {
        return view('livewire.admin.alerts', [
            'alerts' => Alert::withCount('views')->orderByDesc('priority')->latest('id')->get(),
        ]);
    }
}
