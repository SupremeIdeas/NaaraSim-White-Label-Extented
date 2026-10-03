<?php

namespace App\Livewire;

use App\Services\Payouts\Hardening\StepUpAuth;
use App\Services\Payouts\PayoutException;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Verify it's you" card (Addendum D-3.5). Shown above the payout-account and withdrawal forms only when
 * the owner has switched step-up on and the user has no fresh check. The server enforces it regardless of
 * what this card shows (PayoutAccountService / PayoutAdmission) — the card is just the way to pass it.
 */
class PayoutStepUp extends Component
{
    public string $code = '';

    public ?string $message = null;

    public ?string $error = null;

    public bool $sent = false;

    public function send(StepUpAuth $stepUp): void
    {
        $this->error = $this->message = null;
        try {
            $mode = $stepUp->sendCode(Auth::user());
        } catch (PayoutException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->sent = true;
        $this->message = __($mode === 'authenticator' ? 'payouts.step_up.authenticator' : 'payouts.step_up.sent');
    }

    public function verify(StepUpAuth $stepUp): void
    {
        $this->error = $this->message = null;
        try {
            $ok = $stepUp->verify(Auth::user(), $this->code);
        } catch (PayoutException $e) {
            $this->error = $e->getMessage();

            return;
        }
        if (! $ok) {
            $this->error = __('payouts.step_up.bad');

            return;
        }
        $this->reset('code', 'sent');
        $this->message = __('payouts.step_up.ok', ['minutes' => PayoutSettings::stepUpValidMinutes()]);
    }

    public function render(StepUpAuth $stepUp)
    {
        return view('livewire.payout-step-up', [
            'needed' => $stepUp->required() && ! $stepUp->fresh(Auth::user()),
            'authenticator' => $stepUp->usesAuthenticator(Auth::user()),
        ]);
    }
}
