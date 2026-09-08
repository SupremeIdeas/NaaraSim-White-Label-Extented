<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Referral profit-share (blueprint Section 14.3). Shows the user's referral
 * code + share link and their rewarded referrals. The reward is store credit
 * (a share of profit) — created by the referral engine, read-only here.
 */
#[Layout('components.layouts.customer')]
class Referrals extends Component
{
    public function mount(): void
    {
        $user = auth()->user();
        if (empty($user->referral_code)) {
            $user->forceFill(['referral_code' => Str::upper(Str::random(8))])->save();
        }
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.referrals', [
            'code' => $user->referral_code,
            'link' => url('/?ref='.$user->referral_code),
            'referrals' => $user->referralsMade()->latest()->get(),
        ]);
    }
}
