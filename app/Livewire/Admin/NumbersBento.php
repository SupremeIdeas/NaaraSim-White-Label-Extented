<?php

namespace App\Livewire\Admin;

use App\Models\NumbersBentoCard;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\NumbersBento as NumbersBentoContent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Numbers cards (Numbers V6 §2). Edit each of the six bento cards'
 * badge, title, subtitle, bullets, image and visibility — no deploy. The six
 * keys are fixed (they map to real features + the locked layout), so this is an
 * edit-in-place list, not free create/delete. Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class NumbersBento extends Component
{
    use WithFileUploads;

    /** Per-card editable fields, keyed by card key. */
    public array $form = [];

    /** New image uploads, keyed by card key. */
    public array $images = [];

    /** Platform-wide default view for the Contacts book (list | grid). */
    public string $contacts_default_view = 'list';

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->contacts_default_view = \App\Models\Setting::getValue('contacts.default_view') === 'grid' ? 'grid' : 'list';

        // Ensure the six rows exist (seed from defaults on first visit).
        $defaults = NumbersBentoContent::defaults();
        $order = array_flip(NumbersBentoContent::ORDER);
        foreach ($defaults as $key => $def) {
            $card = NumbersBentoCard::firstOrCreate(['key' => $key], [
                'badge_label' => $def['badge_label'], 'title' => $def['title'],
                'subtitle' => $def['subtitle'], 'bullets' => $def['bullets'],
                'sort_order' => $order[$key] ?? 0, 'is_active' => true,
            ]);

            $this->form[$key] = [
                'badge_label' => $card->badge_label,
                'title' => $card->title,
                'subtitle' => $card->subtitle,
                'bullets' => implode("\n", (array) $card->bullets),
                'is_active' => $card->is_active,
                'image_path' => $card->image_path,
                // 'modal' (default) or 'page'. Only togglable keys (owner
                // request, 2026-09-08) — see App\Support\NumbersBento::TOGGLABLE.
                'display_mode' => $card->display_mode ?? 'modal',
            ];
        }
    }

    /** Save the platform-wide default Contacts view (users can still override). */
    public function saveContactsView(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['contacts_default_view' => 'required|in:list,grid']);
        \App\Models\Setting::setValue('contacts.default_view', $this->contacts_default_view, 'ui');
        Auditor::log('numbers.contacts_view_updated', payload: ['view' => $this->contacts_default_view]);
        $this->saved = 'Contacts default view saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Contacts default view saved.');
    }

    public function save(string $key): void
    {
        abort_unless(isset($this->form[$key]), 404);

        $data = $this->validate([
            "form.{$key}.badge_label" => 'nullable|string|max:24',
            // Frontend-UX-fix blueprint Phase E: the title renders next to a
            // fixed 36px icon in a row that also reserves clearance for the
            // card's absolute-positioned badge — on the narrowest two-up
            // mobile card (~126px content width) that leaves very little
            // room. Reproduced live at the old max:60: a 45-char title left
            // the title's own box only ~16px wide, clipping to nothing under
            // `line-clamp-2`'s `overflow: hidden`. 28 chars comfortably clears
            // the longest current default ("Contact Management", 19 chars)
            // plus headroom for a rebrand, while still wrapping cleanly to 2
            // lines at every card width instead of vanishing.
            "form.{$key}.title" => 'required|string|max:28',
            "form.{$key}.subtitle" => 'required|string|max:500',
            "form.{$key}.bullets" => 'nullable|string|max:400',
            "form.{$key}.display_mode" => 'required|in:modal,page',
            "images.{$key}" => 'nullable|image|mimes:webp,jpg,jpeg,png|max:800',
        ])['form'][$key];

        $card = NumbersBentoCard::where('key', $key)->firstOrFail();

        // Bullets: one per line, max 4, trimmed + de-blanked.
        $bullets = collect(preg_split('/\r?\n/', (string) ($data['bullets'] ?? '')))
            ->map(fn ($b) => trim($b))->filter()->take(4)->values()->all();

        if (! empty($this->images[$key])) {
            $card->image_path = MediaStorage::storePublic($this->images[$key], 'numbers-bento');
            $this->images[$key] = null;
            $this->form[$key]['image_path'] = $card->image_path;
        }

        $card->fill([
            'badge_label' => $data['badge_label'] ? strtoupper(trim($data['badge_label'])) : null,
            'title' => trim($data['title']),
            'subtitle' => trim($data['subtitle']),
            'bullets' => $bullets,
            'is_active' => (bool) ($this->form[$key]['is_active'] ?? true),
            // Only meaningful for a togglable key (verify/rent/line) — stored
            // regardless so a card promoted to togglable later just works.
            'display_mode' => $data['display_mode'] ?? 'modal',
        ])->save();

        NumbersBentoContent::flush();
        Auditor::log('numbers.bento_updated', null, null, ['key' => $key]);
        $this->saved = $key;
    }

    public function toggle(string $key): void
    {
        $this->form[$key]['is_active'] = ! ($this->form[$key]['is_active'] ?? true);
        $this->save($key);
    }

    public function render()
    {
        return view('livewire.admin.numbers-bento', [
            'order' => NumbersBentoContent::ORDER,
        ]);
    }
}
