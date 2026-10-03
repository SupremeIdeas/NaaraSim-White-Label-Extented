<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Messages: a three-panel workspace (owner direction, 2026-10-03; layout only, no third-party look). Left: conversations and a Contacts
 * tab so anyone in the address book is one tap from a new message. Centre: the thread. Right: who this is, with call / message / save
 * and a few facts. The panels are resizable by dragging (handled client-side, remembered per browser). On phones it is one pane at a
 * time with back navigation. Replies reuse the existing SendMessage modal: no second sending pipeline, so billing, provider routing
 * and idempotency are untouched.
 */
#[Layout('components.layouts.customer')]
class Messages extends Component
{
    /** The active conversation's counterpart number (deep-linkable). */
    #[Url(as: 'to')]
    public ?string $active = null;

    /** Left panel tab: chats | contacts. */
    public string $tab = 'chats';

    public string $search = '';

    public function mount(): void
    {
        if ($this->active) {
            $this->openThread($this->active);
        }
    }

    public function openThread(string $counterpart): void
    {
        $this->active = Contact::normalizePhone($counterpart) ?: $counterpart;

        MessageThread::where('user_id', Auth::id())
            ->where('counterpart_number', $this->active)
            ->first()?->markRead();
    }

    /** Start (or resume) a conversation with someone from the Contacts tab. */
    public function startWith(string $phone): void
    {
        $this->openThread($phone);
        $this->tab = 'chats';
        $this->search = '';
    }

    public function closeThread(): void
    {
        $this->active = null;
    }

    /** A merged, chronological timeline for the active conversation. */
    private function timeline(): \Illuminate\Support\Collection
    {
        if (! $this->active) {
            return collect();
        }
        $uid = Auth::id();

        $in = InboundMessage::where('user_id', $uid)->where('from_number', $this->active)
            ->get()->map(fn ($m) => (object) [
                'direction' => 'in', 'body' => $m->body, 'attachment_url' => $m->attachment_url,
                'is_voicemail' => $m->voicemail_path !== null,
                'at' => $m->received_at ?? $m->created_at,
            ]);
        $out = OutboundMessage::where('user_id', $uid)->where('to_number', $this->active)
            ->get()->map(fn ($m) => (object) [
                'direction' => 'out', 'body' => $m->body, 'attachment_url' => $m->attachment_url,
                'is_voicemail' => false,
                'at' => $m->created_at,
            ]);

        return $in->concat($out)->sortBy('at')->values();
    }

    public function render()
    {
        $uid = Auth::id();
        $contacts = Contact::where('user_id', $uid)->orderByDesc('is_favorite')->orderBy('name')->get();
        $names = $contacts->pluck('name', 'phone_number');
        $needle = mb_strtolower(trim($this->search));

        $threads = MessageThread::where('user_id', $uid)->orderByDesc('last_at')->limit(100)->get();
        if ($needle !== '') {
            $threads = $threads->filter(fn ($t) => str_contains(mb_strtolower($t->counterpart_number.' '.($names[$t->counterpart_number] ?? '').' '.$t->last_body), $needle))->values();
        }
        $shownContacts = $needle === '' ? $contacts : $contacts->filter(fn ($c) => str_contains(mb_strtolower($c->name.' '.$c->phone_number), $needle))->values();

        $timeline = $this->timeline();
        $info = null;
        if ($this->active) {
            $info = [
                'contact' => $contacts->firstWhere('phone_number', $this->active),
                'incoming' => $timeline->where('direction', 'in')->count(),
                'outgoing' => $timeline->where('direction', 'out')->count(),
                'first' => optional($timeline->first())->at,
                'last' => optional($timeline->last())->at,
            ];
        }

        return view('livewire.messages', [
            'threads' => $threads,
            'contacts' => $shownContacts,
            'names' => $names,
            'timeline' => $timeline,
            'info' => $info,
        ]);
    }
}
