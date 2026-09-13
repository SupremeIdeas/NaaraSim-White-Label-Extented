<?php

namespace App\Livewire;

use App\Jobs\AlertAdminJob;
use App\Models\PortInRequest;
use App\Services\SMS\PortabilityChecker;
use App\Support\Auditor;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * US/Canada port-in intake (Prompt 11). Honest by design: bringing a number in
 * is a multi-day, human-reviewed carrier process — never instant provisioning —
 * so this captures the request + the losing-carrier details and tracks its
 * status.
 *
 * Eligibility is checked with a REAL provider probe (PortabilityChecker →
 * Twilio Portability API) before we ask for anything else: a number our
 * providers can actually bring in sails straight through; one we can't verify
 * gets an honest "sorry", never a promise we can't keep. Nothing is charged
 * here — billing only ever starts if/when the port completes, a later step.
 */
#[Layout('components.layouts.customer')]
class PortIn extends Component
{
    public string $phone_number = '';

    public string $account_number = '';

    public string $pin = '';

    public string $billing_name = '';

    public string $billing_address = '';

    public string $notes = '';

    /** null = not yet checked; true/false = probe result for the current number. */
    public ?bool $eligible = null;

    public bool $pinRequired = true;

    public string $eligibilityMessage = '';

    /** Changing the number invalidates any prior eligibility check. */
    public function updatedPhoneNumber(): void
    {
        $this->eligible = null;
        $this->eligibilityMessage = '';
    }

    /**
     * Step 1 — probe the carrier for this specific number. Only a confirmed
     * "portable" reveals the rest of the form; anything else shows the honest
     * sorry message.
     */
    public function checkEligibility(PortabilityChecker $checker): void
    {
        $this->validate(['phone_number' => ['required', 'string', 'max:20']]);

        $result = $checker->checkPortIn($this->phone_number);
        $this->eligible = $result['eligible'];
        $this->pinRequired = $result['pin_required'];
        $this->eligibilityMessage = (string) ($result['reason'] ?? '');

        Auditor::log('port_in.eligibility_checked', 'PortInRequest', null, [
            'phone_number' => $this->phone_number,
            'eligible' => $this->eligible,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'regex:/^\+1\d{10}$/'],
            'account_number' => [$this->pinRequired ? 'required' : 'nullable', 'string', 'max:60'],
            'pin' => [$this->pinRequired ? 'required' : 'nullable', 'string', 'max:40'],
            'billing_name' => ['required', 'string', 'max:120'],
            'billing_address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'phone_number.regex' => 'Only US & Canada numbers (+1) can be brought in right now.',
        ];
    }

    public function submit(PortabilityChecker $checker): void
    {
        $data = $this->validate();

        // Re-probe server-side — never trust a client-side "eligible" flag. A
        // number that isn't verifiably portable is refused here regardless of
        // what the form state claims.
        $result = $checker->checkPortIn($data['phone_number']);
        if (! $result['eligible']) {
            $this->eligible = false;
            $this->eligibilityMessage = (string) ($result['reason'] ?? 'Sorry — this number can\'t be brought in.');

            return;
        }

        // One open request per number, per user — don't let a double-submit
        // create duplicate carrier orders.
        $existing = PortInRequest::where('user_id', auth()->id())
            ->where('phone_number', $data['phone_number'])
            ->whereNotIn('status', [PortInRequest::STATUS_COMPLETED, PortInRequest::STATUS_REJECTED])
            ->exists();

        if ($existing) {
            $this->dispatch('nx-toast', type: 'info',
                message: 'You already have a port-in request in progress for that number.');

            return;
        }

        $request = PortInRequest::create([
            'user_id' => auth()->id(),
            'phone_number' => $data['phone_number'],
            'status' => PortInRequest::STATUS_SUBMITTED,
            'account_number' => $data['account_number'] ?: null,
            'pin' => $data['pin'] ?: null,
            'billing_name' => $data['billing_name'],
            'billing_address' => $data['billing_address'],
            'notes' => $data['notes'] ?: null,
        ]);

        Auditor::log('port_in.requested', 'PortInRequest', $request->id, ['phone_number' => $data['phone_number']]);
        AlertAdminJob::dispatch(
            code: 'port_in_requested',
            message: 'A customer submitted a port-in request.',
            context: ['user_id' => auth()->id(), 'port_in_request_id' => $request->id, 'phone_number' => $data['phone_number']],
            severity: 'info',
        );

        $this->reset(['phone_number', 'account_number', 'pin', 'billing_name', 'billing_address', 'notes', 'eligible', 'eligibilityMessage']);
        $this->dispatch('nx-toast', type: 'success',
            message: 'Port-in request submitted. Bringing a number in usually takes 5–15 business days — we\'ll keep you posted by email.');
    }

    public function render()
    {
        return view('livewire.port-in', [
            'requests' => PortInRequest::where('user_id', auth()->id())
                ->latest()->limit(20)->get(),
        ]);
    }
}
