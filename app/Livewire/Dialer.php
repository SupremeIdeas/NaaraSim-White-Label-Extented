<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Models\VoiceCall;
use App\Services\Voice\SpamReportService;
use App\Services\Voice\VoiceDialerService;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * In-browser international dialer (Live Voice — Part B). A softphone: the user
 * types a number and calls it over WebRTC (Twilio Voice JS SDK) with no app or
 * physical phone. Livewire owns the MONEY (quote + pre-authorisation hold +
 * settlement via VoiceDialerService); Alpine + the SDK own the live audio.
 *
 * Feature-gated on the existing Twilio provider status (voice rides the same
 * keys) — the whole screen 404s / is hidden until Twilio is Active, no new
 * toggle. The wallet is charged upfront for a funded block and the unused
 * minutes are refunded on hang-up, so a user can never overspend a call.
 */
#[Layout('components.layouts.customer')]
class Dialer extends Component
{
    public string $destination = '';

    public ?string $error = null;

    /** Live quote (retail only — cost is never surfaced). */
    public bool $quoted = false;

    public ?float $ratePerMin = null;

    public ?int $fundedMinutes = null;

    public function mount(): void
    {
        abort_unless(ProviderStatus::isActive('twilio'), 404);

        // "Tap a name, call it" (Part C): a contact's Call button links here with
        // ?to=, prefilling the destination field.
        $to = (string) request()->query('to', '');
        if ($to !== '' && preg_match('/^\+?[0-9]{6,15}$/', $to)) {
            $this->destination = \App\Models\Contact::normalizePhone($to);
        }
    }

    /** Live per-minute retail + how many minutes the wallet can fund right now. */
    public function prepare(VoiceDialerService $dialer, SpamReportService $spam): void
    {
        $this->error = null;
        $this->reset('quoted', 'ratePerMin', 'fundedMinutes');

        $destination = trim($this->destination);
        if (! preg_match('/^\+[1-9]\d{6,14}$/', $destination)) {
            $this->error = 'Enter a valid number in international format, e.g. +2348012345678.';

            return;
        }

        // Spam-report auto-block (Prompt 11): an honest refusal here, before
        // wasting a quote — the authoritative check still runs in
        // VoiceDialerService::begin() before any wallet hold.
        if ($spam->isBlocked($destination)) {
            $this->error = 'This number has been reported as spam by multiple users and can’t be dialed.';

            return;
        }

        $quote = $dialer->quote(Auth::user(), $this->destination);
        $this->ratePerMin = $quote['retail_per_min'];
        $this->fundedMinutes = $quote['funded_minutes'];
        $this->quoted = true;

        if ($this->fundedMinutes < 1) {
            $this->error = 'Your wallet balance is too low to start a call. Top up and try again.';
            $this->quoted = false;
        }
    }

    /**
     * Pre-authorise the funded block (atomic wallet hold) and hand the browser
     * everything it needs to open the WebRTC call. The actual audio connection is
     * made by Alpine + the Twilio SDK; billing is already reserved here.
     */
    public function dial(VoiceDialerService $dialer): void
    {
        abort_unless(ProviderStatus::isActive('twilio'), 404);
        $this->error = null;

        try {
            $call = $dialer->begin(Auth::user(), $this->destination);
        } catch (InsufficientBalanceException $e) {
            $this->error = 'Your wallet balance is too low to start a call. Top up and try again.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Not enough balance',
                message: 'You were not charged. Top up your wallet to place the call.',
                cta: ['label' => 'Top up wallet', 'href' => route('wallet')]);

            return;
        } catch (SmsException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('quoted', 'ratePerMin', 'fundedMinutes');

        // If the number is in the address book, surface the saved name on the
        // in-call screen (falls back to the raw number when it isn't).
        $peerName = \App\Models\Contact::where('user_id', Auth::id())
            ->where('phone_number', $call->destination)->value('name');

        // Alpine picks this up: fetch a token, then Device.connect(...).
        $this->dispatch('voice-dial',
            callId: $call->id,
            destination: $call->destination,
            peerName: (string) ($peerName ?? ''),
            fundedSeconds: $dialer->fundedSeconds($call),
        );
    }

    /**
     * Client-side settlement fallback (the authoritative settle is the Twilio
     * status webhook). Idempotent — if the webhook already settled, this no-ops.
     */
    public function settleCall(int $callId, int $seconds, string $status, VoiceDialerService $dialer): void
    {
        $call = VoiceCall::where('user_id', Auth::id())->find($callId);
        if ($call !== null) {
            $dialer->settle($call, max(0, $seconds), $status === 'completed' ? 'completed' : 'no-answer');
        }
    }

    /** Spam-report + auto-block (Prompt 11): flag a recent call's destination. */
    public function reportSpam(int $callId, SpamReportService $spam): void
    {
        $call = VoiceCall::where('user_id', Auth::id())->find($callId);
        if ($call === null) {
            return;
        }

        $spam->report($call->destination, Auth::user(), 'dialer');
        $this->dispatch('nx-toast', type: 'success', message: 'Reported. Thanks for helping keep Naara safe.');
    }

    public function render()
    {
        $recent = VoiceCall::where('user_id', Auth::id())
            ->whereNotNull('settled_at')
            ->latest('id')->limit(8)->get();

        // A quick-pick strip of saved contacts (Part C) — tap to fill the field.
        $contacts = \App\Models\Contact::where('user_id', Auth::id())
            ->orderBy('name')->limit(12)->get();

        // §3 country picker: pick the country + key the local number instead of
        // hand-typing +<code>. Opens on the caller's own country (from their
        // profile), falling back to Nigeria.
        $user = Auth::user();
        $dialCountries = \App\Support\DialCodes::all();
        $defaultCountry = \App\Support\DialCodes::default($user->country_code ?? null);

        // §3 wallet on the dialer: the mobile /numbers/* header shows the balance,
        // but that bar is lg:hidden — surface it here too so desktop callers see
        // their funds without leaving the dialer. Retail-side only, never cost.
        $walletUsd = (float) ($user->wallet?->usd_balance ?? 0);

        return view('livewire.dialer', [
            'recent' => $recent,
            'contacts' => $contacts,
            'dialCountries' => $dialCountries,
            'defaultCountry' => $defaultCountry,
            'walletUsd' => $walletUsd,
        ]);
    }
}
