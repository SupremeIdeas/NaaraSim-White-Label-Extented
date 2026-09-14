<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Services\Voice\SpamReportService;
use App\Support\ContactImport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * In-app contact book (Live Voice — Part C). A per-user address book that feeds
 * the dialer — "tap a name, call it". Not a provider-billed feature, so there's
 * NO feature gate; just standard auth-scoped CRUD, plus CSV/vCard bulk import.
 * The optional "Import from phone" shortcut (Android Chrome only) posts into the
 * same book via importPicked — it's a convenience, never the primary path, so
 * the book works identically on desktop and iOS Safari.
 */
#[Layout('components.layouts.customer')]
class Contacts extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $phone = '';

    public ?int $editingId = null;

    public string $search = '';

    /** CSV or vCard file for bulk import. */
    public $upload = null;

    public ?string $notice = null;

    public function save(): void
    {
        $this->notice = null;
        $this->validate(['name' => ['required', 'string', 'max:120']]);

        // Normalise FIRST (real address books carry spaces / dashes / parens),
        // then validate the cleaned number so tidy input isn't rejected.
        $phone = Contact::normalizePhone($this->phone);
        $digits = ltrim($phone, '+');
        if (! ctype_digit($digits) || strlen($digits) < 6 || strlen($digits) > 15) {
            $this->addError('phone', 'Enter a valid number in international format, e.g. +2348012345678.');

            return;
        }

        // updateOrCreate on (user, phone) keeps the book free of duplicates.
        $contact = Contact::updateOrCreate(
            ['user_id' => Auth::id(), 'phone_number' => $phone],
            ['name' => trim($this->name)],
        );

        // If we were editing a row whose number changed, drop the stale one.
        if ($this->editingId && $this->editingId !== $contact->id) {
            Contact::where('user_id', Auth::id())->whereKey($this->editingId)->delete();
        }

        $this->reset('name', 'phone', 'editingId');
        $this->dispatch('contact-saved'); // closes the sheet
        $this->dispatch('nx-toast', type: 'success', message: 'Contact saved.');
    }

    public function edit(int $id): void
    {
        $contact = Contact::where('user_id', Auth::id())->findOrFail($id);
        $this->editingId = $contact->id;
        $this->name = $contact->name;
        $this->phone = $contact->phone_number;
    }

    public function cancelEdit(): void
    {
        $this->reset('name', 'phone', 'editingId');
    }

    public function delete(int $id): void
    {
        Contact::where('user_id', Auth::id())->whereKey($id)->delete();
        if ($this->editingId === $id) {
            $this->reset('name', 'phone', 'editingId');
        }
        $this->dispatch('nx-toast', type: 'success', message: 'Contact removed.');
    }

    /** Star / unstar a contact for the favourites row. */
    public function toggleFavorite(int $id): void
    {
        $contact = Contact::where('user_id', Auth::id())->findOrFail($id);
        $contact->update(['is_favorite' => ! $contact->is_favorite]);
    }

    /**
     * Spam-report + auto-block (Prompt 11): flag a contact's number as spam.
     * Owner-scoped read of the contact, but the report itself is keyed on the
     * NUMBER (not the contact row) — it feeds the same platform-wide count as
     * a report made from the Dialer.
     */
    public function reportSpam(int $id, SpamReportService $spam): void
    {
        $contact = Contact::where('user_id', Auth::id())->find($id);
        if ($contact === null) {
            return;
        }

        $spam->report($contact->phone_number, Auth::user(), 'contacts');
        $this->dispatch('nx-toast', type: 'success', message: 'Reported. Thanks for helping keep Naara safe.');
    }

    /** Bulk import from a CSV or vCard file. */
    public function import(): void
    {
        $this->notice = null;
        $this->validate([
            'upload' => ['required', 'file', 'max:2048', 'mimes:csv,txt,vcf'],
        ], [
            'upload.mimes' => 'Upload a .csv or .vcf (vCard) file.',
        ]);

        $extension = strtolower($this->upload->getClientOriginalExtension());
        $contents = (string) file_get_contents($this->upload->getRealPath());
        $rows = ContactImport::parse($contents, $extension);

        $imported = $this->storeRows($rows);

        $this->reset('upload');
        $this->notice = $imported === 0
            ? 'No contacts found in that file.'
            : $imported.' contact'.($imported === 1 ? '' : 's').' imported.';
        $this->dispatch('nx-toast', type: 'success', message: $this->notice);
    }

    /**
     * Progressive enhancement (Android Chrome only): rows picked from the OS
     * Contact Picker API are posted here and stored in the SAME book. Never the
     * sole path — desktop / iOS Safari use manual add + file import.
     *
     * @param  array<int, array{name?: string, phone?: string}>  $picked
     */
    public function importPicked(array $picked): void
    {
        $rows = [];
        foreach ($picked as $entry) {
            $phone = Contact::normalizePhone((string) ($entry['phone'] ?? ''));
            if ($phone === '' || $phone === '+') {
                continue;
            }
            $name = trim((string) ($entry['name'] ?? '')) ?: $phone;
            $rows[$phone] = ['name' => mb_substr($name, 0, 120), 'phone' => mb_substr($phone, 0, 32)];
        }

        $imported = $this->storeRows(array_values($rows));
        if ($imported > 0) {
            $this->dispatch('nx-toast', type: 'success', message: $imported.' contact'.($imported === 1 ? '' : 's').' imported from your phone.');
        }
    }

    /** Persist parsed rows, de-duped on (user, phone). Returns the count. */
    private function storeRows(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            Contact::updateOrCreate(
                ['user_id' => Auth::id(), 'phone_number' => $row['phone']],
                ['name' => $row['name']],
            );
            $count++;
        }

        return $count;
    }

    public function render()
    {
        $all = Contact::where('user_id', Auth::id())
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('phone_number', 'like', $term));
            })
            ->orderByRaw('LOWER(name)')->get();

        // Group A–Z by first letter (non-letters bucket under '#') for the
        // sectioned list + the quick-scroll index.
        $grouped = $all->groupBy(function (Contact $c) {
            $first = mb_strtoupper(mb_substr(trim($c->name), 0, 1));

            return preg_match('/[A-Z]/', $first) ? $first : '#';
        })->sortKeys();

        // Message action is only offered when the user owns a Naara Line (an SMS
        // needs a real "from" number) — Numbers V6 §6.
        $ownsLine = \App\Models\VirtualNumber::where('user_id', Auth::id())
            ->where('status', 'active')->exists();

        // Platform-wide default view (admin-set); a user's own toggle still wins
        // once they pick one (it persists locally).
        $defaultView = \App\Models\Setting::getValue('contacts.default_view') === 'grid' ? 'grid' : 'list';

        return view('livewire.contacts', [
            'total' => $all->count(),
            'favorites' => $all->where('is_favorite', true)->values(),
            'grouped' => $grouped,
            'letters' => $grouped->keys(),
            'ownsLine' => $ownsLine,
            'twilioActive' => \App\Support\ProviderStatus::isActive('twilio'),
            'defaultView' => $defaultView,
        ]);
    }
}
