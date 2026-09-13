<?php

namespace App\Livewire;

use App\Jobs\AlertAdminJob;
use App\Models\PortInRequest;
use App\Support\Auditor;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * US/Canada port-in intake (Prompt 11). Honest by design: bringing a number in
 * is a multi-day, human-reviewed carrier process — never instant provisioning —
 * so this captures the request + the losing-carrier details and tracks its
 * status. Only +1 (US/Canada) numbers are portable via our providers (the
 * audit finding); anything else is refused up front rather than promising a
 * port we can't facilitate. Nothing is charged here — billing only ever starts
 * if/when the port completes, a deliberate later step.
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

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // US/Canada E.164: +1 then 10 digits. The audit's honest boundary —
            // we never accept a number we can't actually port in.
            'phone_number' => ['required', 'string', 'regex:/^\+1\d{10}$/'],
            'account_number' => ['required', 'string', 'max:60'],
            'pin' => ['required', 'string', 'max:40'],
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

    public function submit(): void
    {
        $data = $this->validate();

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
            'account_number' => $data['account_number'],
            'pin' => $data['pin'],
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

        $this->reset(['phone_number', 'account_number', 'pin', 'billing_name', 'billing_address', 'notes']);
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
