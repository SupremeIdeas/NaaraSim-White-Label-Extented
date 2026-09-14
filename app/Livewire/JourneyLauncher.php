<?php

namespace App\Livewire;

use App\Services\Credits\CreditService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Floating "My Journey" launcher (owner request) — a Wizard-sized, same-
 * minimize-behavior companion widget for fast, on-demand navigation to the
 * user's activity-progress and rewards page (route `journey`) without going
 * through the menu. The glance badge shows the one cheap, real metric worth
 * showing on every page load: the user's current NaaraCredits balance
 * (CreditService::balance — a single query, no invented aggregate).
 */
class JourneyLauncher extends Component
{
    public function render()
    {
        $user = Auth::user();

        return view('livewire.journey-launcher', [
            'creditsBalance' => $user ? app(CreditService::class)->balance($user) : 0.0,
        ]);
    }
}
