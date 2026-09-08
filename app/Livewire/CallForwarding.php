<?php

namespace App\Livewire;

use App\Jobs\SyncVoiceWebhookJob;
use App\Models\CallForwardingRule;
use App\Models\VirtualNumber;
use App\Support\Auditor;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Call forwarding for permanent NaaraSim numbers (Live Voice — Part A). A user
 * points inbound calls on their number at their real phone. Feature-gated on the
 * existing Twilio provider status (voice rides the same credentials) — the whole
 * screen is "Coming Soon" / hidden until Twilio is Active, no new toggle.
 */
#[Layout('components.layouts.customer')]
class CallForwarding extends Component
{
    public ?int $numberId = null;

    public string $forwardTo = '';

    public string $fallback = '';

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(ProviderStatus::isActive('twilio'), 404);
    }

    /** The user's provisioned, voice-capable permanent numbers. */
    private function myNumbers()
    {
        return VirtualNumber::query()
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->latest('id')->get();
    }

    public function edit(int $numberId): void
    {
        $this->numberId = $numberId;
        // Owner-scoped: numberId arrives from the client, and the rule exposes
        // the user's private forward-to / fallback numbers — an unscoped lookup
        // would disclose another user's forwarding destinations.
        $rule = CallForwardingRule::where('user_id', Auth::id())
            ->where('virtual_number_id', $numberId)->first();
        $this->forwardTo = $rule?->forward_to_number ?? '';
        $this->fallback = $rule?->fallback_number ?? '';
        $this->error = null;
    }

    public function save(): void
    {
        abort_unless(ProviderStatus::isActive('twilio'), 404);
        $this->error = null;
        $this->validate([
            'numberId' => 'required|integer',
            'forwardTo' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'fallback' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
        ], [
            'forwardTo.regex' => 'Enter a valid number in international format, e.g. +2348012345678.',
            'fallback.regex' => 'Enter a valid international number, or leave blank.',
        ]);

        $number = VirtualNumber::where('user_id', Auth::id())->find($this->numberId);
        if ($number === null) {
            $this->error = 'Number not found.';

            return;
        }

        $rule = CallForwardingRule::updateOrCreate(
            ['twilio_number' => $number->phone_number],
            [
                'user_id' => Auth::id(),
                'virtual_number_id' => $number->id,
                'forward_to_number' => $this->forwardTo,
                'fallback_number' => $this->fallback ?: null,
                'status' => CallForwardingRule::ACTIVE,
            ],
        );

        // Point the number's inbound Voice URL at our TwiML webhook (queued).
        if ($number->sid) {
            SyncVoiceWebhookJob::dispatch($number->sid, route('webhooks.twilio.voice'), attach: true);
        }
        Auditor::log('voice.forwarding_set', 'CallForwardingRule', $rule->id, ['number_id' => $number->id]);

        $this->reset('numberId', 'forwardTo', 'fallback');
        $this->dispatch('nx-toast', type: 'success', message: 'Call forwarding is on for '.$number->phone_number.'.');
    }

    public function disable(int $ruleId): void
    {
        $rule = CallForwardingRule::where('user_id', Auth::id())->findOrFail($ruleId);
        $rule->update(['status' => CallForwardingRule::INACTIVE]);

        if ($rule->virtualNumber?->sid) {
            SyncVoiceWebhookJob::dispatch($rule->virtualNumber->sid, route('webhooks.twilio.voice'), attach: false);
        }
        Auditor::log('voice.forwarding_disabled', 'CallForwardingRule', $rule->id);
        $this->dispatch('nx-toast', type: 'success', message: 'Call forwarding turned off.');
    }

    public function render()
    {
        $numbers = $this->myNumbers();
        $rules = CallForwardingRule::where('user_id', Auth::id())
            ->get()->keyBy('virtual_number_id');

        return view('livewire.call-forwarding', compact('numbers', 'rules'));
    }
}
