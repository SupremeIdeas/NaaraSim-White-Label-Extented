<?php

namespace App\Livewire\Admin;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Notifications\HumanRepliedNotification;
use App\Services\Support\SupportReply;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Staff support queue (Module 25). Escalated conversations land here; a staff
 * member (tickets.manage) assigns one to themselves, reads the full thread —
 * including the AI's diagnosis and any customer voice note — and replies. A
 * reply to a PAYING customer is also spoken (ElevenLabs) via SupportReply.
 */
#[Layout('components.layouts.admin')]
class SupportQueue extends Component
{
    public ?int $selectedId = null;

    public string $reply = '';

    public ?string $flash = null;

    /**
     * Re-authorize on every request with the SAME predicate the route enforces
     * (`permission:tickets.manage`; super_admin bypasses via Gate::before). The
     * shared `/livewire/update` endpoint does not re-run route permission
     * middleware, so assignToMe/sendReply/resolve — which reply to customers and
     * mutate tickets — are re-checked here on load AND every update.
     */
    public function booted(): void
    {
        abort_unless(auth()->user()?->can('tickets.manage'), 403);
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->reply = '';
    }

    public function assignToMe(int $id): void
    {
        $c = $this->ticket($id);
        $c->forceFill(['assigned_to' => Auth::id(), 'status' => 'assigned'])->save();
        $this->selectedId = $id;
        Auditor::log('support.ticket_assigned', SupportConversation::class, $id);
        $this->flash = 'Assigned to you.';
    }

    public function sendReply(SupportReply $replies): void
    {
        $text = trim($this->reply);
        if ($text === '' || ! $this->selectedId) {
            return;
        }

        $c = $this->ticket($this->selectedId);

        // Take ownership on first reply if unassigned.
        if (is_null($c->assigned_to)) {
            $c->assigned_to = Auth::id();
        }
        $c->forceFill(['status' => 'assigned', 'last_human_reply_at' => now()])->save();

        $replies->deliver($c, 'staff', $text);
        $this->reply = '';

        try {
            $c->user->notify(new HumanRepliedNotification);
        } catch (\Throwable) {
            // best-effort
        }

        Auditor::log('support.ticket_reply', SupportConversation::class, $c->id);
    }

    public function resolve(int $id): void
    {
        $this->ticket($id)->forceFill(['status' => 'resolved'])->save();
        Auditor::log('support.ticket_resolved', SupportConversation::class, $id);
        $this->flash = 'Marked resolved.';
    }

    private function ticket(int $id): SupportConversation
    {
        return SupportConversation::findOrFail($id);
    }

    public function render()
    {
        // Queue: escalated or human-owned tickets that aren't closed/resolved.
        $tickets = SupportConversation::with('user')
            ->where(function ($q) {
                $q->where('escalated', true)->orWhereIn('status', ['assigned']);
            })
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderByDesc('escalated_at')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $thread = collect();
        $selected = null;
        if ($this->selectedId) {
            $selected = SupportConversation::with('user', 'assignee')->find($this->selectedId);
            if ($selected) {
                $thread = $selected->messages()->orderBy('id')->get()
                    ->map(fn (SupportMessage $m) => [
                        'role' => $m->role,
                        'body' => $m->body,
                        'voice' => $m->voice_path ? route('support.voice', $m->id) : null,
                        'attachment' => $m->attachment_path ? route('support.attachment', $m->id) : null,
                        'attachment_name' => $m->attachment_name,
                        'attachment_image' => \App\Support\SupportAttachment::isImage($m->attachment_mime),
                    ]);
            }
        }

        return view('livewire.admin.support-queue', [
            'tickets' => $tickets,
            'selected' => $selected,
            'thread' => $thread,
            // What the AI already did on this ticket (so staff aren't blind to it).
            'autopilotLog' => $selected?->autopilot_log ?? [],
        ]);
    }
}
