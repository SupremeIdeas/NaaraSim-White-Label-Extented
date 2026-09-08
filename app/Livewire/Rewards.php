<?php

namespace App\Livewire;

use App\Jobs\EvaluateJourneyGoalsJob;
use App\Models\CreditLedger;
use App\Services\Credits\CreditService;
use App\Support\CreditSettings;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NaaraCredits rewards area (loyalty module). Opt-in earning — a normal customer
 * never sees an ad; only someone who comes here to earn does. Shows the credit
 * balance (+ its USD value), a daily check-in, the referral link, and — only
 * when the admin has configured a compliant rewarded-ad provider — an
 * "earn by watching an ad" launcher whose reward is granted server-side via the
 * network's postback. Nothing here is ever forced on the user.
 */
#[Layout('components.layouts.customer')]
class Rewards extends Component
{
    public ?string $flash = null;

    public function checkIn(CreditService $credits): void
    {
        $user = Auth::user()->fresh();
        $earned = $credits->checkIn($user);
        if ($earned > 0) {
            $this->flash = "You earned {$earned} NaaraCredits. Come back tomorrow for more!";
            // Hero toast — dispatched only after the credits are committed.
            $this->dispatch('nx-toast', variant: 'hero', type: 'success',
                title: 'Reward earned',
                message: "+{$earned} NaaraCredits added to your balance. Come back tomorrow for more!");
            // Celebratory confetti (self-hosted Lottie), only on a real earn.
            $this->dispatch('reward-claimed');
            // My Journey goals (loyalty expansion) — a streak goal can unlock
            // the instant today's check-in lands.
            EvaluateJourneyGoalsJob::dispatch($user->id);
        } else {
            $this->flash = 'You’ve already checked in — come back later for your next reward.';
            $this->dispatch('nx-toast', type: 'info', message: $this->flash);
        }
    }

    /** Signed offerwall URL for this user (the network attributes + posts back). */
    public function offerwallUrl(): ?string
    {
        if (! CreditSettings::adsActive()) {
            return null;
        }
        $base = (string) CreditSettings::get('ad_offerwall_url', '');
        $user = (string) Auth::id();
        $sig = hash_hmac('sha256', $user, (string) config('services.offerwall.postback_secret'));
        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.'user='.urlencode($user).'&sig='.$sig;
    }

    public function render(CreditService $credits)
    {
        $user = Auth::user();

        return view('livewire.rewards', [
            'enabled' => CreditSettings::enabled(),
            'balance' => $credits->balance($user),
            'usdValue' => CreditSettings::creditsToUsd($credits->balance($user)),
            'canWithdraw' => PayoutSettings::enabled() && $credits->withdrawableBalance($user) > 0,
            'withdrawableUsd' => CreditSettings::creditsToUsd($credits->withdrawableBalance($user)),
            'perUsd' => CreditSettings::perUsd(),
            'canCheckIn' => $credits->canCheckIn($user),
            'checkinDaily' => (int) CreditSettings::get('checkin_daily', 5),
            'adsActive' => CreditSettings::adsActive(),
            'offerwallUrl' => $this->offerwallUrl(),
            'ledger' => CreditLedger::where('user_id', $user->id)->latest()->limit(15)->get(),
        ]);
    }
}
