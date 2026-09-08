<?php

namespace App\Livewire;

use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Conversation inbox (Numbers overhaul §1). Thread list grouped by counterpart
 * (from the MessageThread summary — cheap indexed read), and a selected thread
 * that merges inbound + outbound chronologically. Two-pane on desktop, single-
 * pane-with-navigation on mobile (§6). Replies reuse the existing SendMessage
 * modal (dispatched with the thread's counterpart) — no second composer.
 */
#[Layout('components.layouts.customer')]
class Messages extends Component
{
    /** The active conversation's counterpart number (deep-linkable). */
    #[Url(as: 'to')]
    public ?string $active = null;

    public function mount(): void
    {
        if ($this->active) {
            $this->openThread($this->active);
        }
    }

    public function openThread(string $counterpart): void
    {
        $this->active = $counterpart;

        MessageThread::where('user_id', Auth::id())
            ->where('counterpart_number', $counterpart)
            ->first()?->markRead();
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
                'at' => $m->received_at ?? $m->created_at,
            ]);
        $out = OutboundMessage::where('user_id', $uid)->where('to_number', $this->active)
            ->get()->map(fn ($m) => (object) [
                'direction' => 'out', 'body' => $m->body, 'attachment_url' => $m->attachment_url,
                'at' => $m->created_at,
            ]);

        return $in->concat($out)->sortBy('at')->values();
    }

    public function render()
    {
        return view('livewire.messages', [
            'threads' => MessageThread::where('user_id', Auth::id())
                ->orderByDesc('last_at')->limit(100)->get(),
            'timeline' => $this->timeline(),
        ]);
    }
}
