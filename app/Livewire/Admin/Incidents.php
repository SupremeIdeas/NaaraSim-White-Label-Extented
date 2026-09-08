<?php

namespace App\Livewire\Admin;

use App\Models\Incident;
use App\Models\IncidentUpdate;
use App\Models\StatusSubscriber;
use App\Notifications\StatusUpdateNotification;
use App\Support\Auditor;
use App\Support\StatusPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Status incidents (Status page §1). Post an incident, add timeline
 * updates, resolve it — each change fans out to subscribers by email (queued).
 * Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class Incidents extends Component
{
    // New incident form
    public string $title = '';
    public string $component = '';
    public string $impact = 'minor';
    public string $body = '';

    // Per-incident update form (keyed by incident id)
    public array $update = [];

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function create(): void
    {
        $data = $this->validate([
            'title' => 'required|string|max:140',
            'component' => 'nullable|string|max:40',
            'impact' => 'required|in:'.implode(',', Incident::IMPACTS),
            'body' => 'required|string|max:2000',
        ]);

        $incident = Incident::create([
            'title' => $data['title'],
            'component' => $data['component'] ?: null,
            'impact' => $data['impact'],
            'status' => 'investigating',
            'started_at' => now(),
            'created_by' => Auth::id(),
        ]);
        $incident->updates()->create(['status' => 'investigating', 'body' => $data['body']]);

        $this->notifySubscribers($incident);
        Auditor::log('status.incident_opened', Incident::class, $incident->id, ['title' => $incident->title]);
        $this->reset('title', 'component', 'impact', 'body');
        $this->dispatch('nx-toast', type: 'success', message: 'Incident posted.');
    }

    public function postUpdate(int $incidentId): void
    {
        $incident = Incident::findOrFail($incidentId);
        $status = $this->update[$incidentId]['status'] ?? 'monitoring';
        $body = trim((string) ($this->update[$incidentId]['body'] ?? ''));
        abort_unless(in_array($status, Incident::STATUSES, true), 422);
        if ($body === '') {
            $this->addError('update.'.$incidentId.'.body', 'Add a message.');

            return;
        }

        $incident->updates()->create(['status' => $status, 'body' => $body]);
        $incident->status = $status;
        if ($status === 'resolved') {
            $incident->resolved_at = now();
        }
        $incident->save();

        $this->notifySubscribers($incident);
        $this->update[$incidentId] = ['status' => 'monitoring', 'body' => ''];
        Auditor::log('status.incident_updated', Incident::class, $incident->id, ['status' => $status]);
        $this->dispatch('nx-toast', type: 'success', message: 'Update posted.');
    }

    private function notifySubscribers(Incident $incident): void
    {
        StatusSubscriber::where('is_active', true)->whereNotNull('email')
            ->chunk(200, function ($subs) use ($incident) {
                foreach ($subs as $sub) {
                    Notification::route('mail', $sub->email)
                        ->notify(new StatusUpdateNotification($incident, $sub->token));
                }
            });
    }

    public function render()
    {
        return view('livewire.admin.incidents', [
            'incidents' => Incident::with('updates')->latest('started_at')->limit(30)->get(),
            'components' => StatusPage::components(),
            'subscriberCount' => StatusSubscriber::where('is_active', true)->count(),
        ]);
    }
}
