<?php

namespace App\Http\Controllers;

use App\Support\AppExport;

/**
 * First-run onboarding (App Export). 3–4 admin-managed portrait slides the user
 * swipes/Next through on first app open, landing on the LOGIN page — the right
 * gateway into the dashboard, not the marketing homepage. If onboarding is off
 * or empty, we send them straight to login so there's never a dead screen.
 */
class OnboardingController extends Controller
{
    public function __invoke()
    {
        // Already signed in → onboarding isn't for you.
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }
        if (! AppExport::onboardingEnabled()) {
            return redirect()->route('login');
        }

        return view('marketing.onboarding', [
            'slides' => AppExport::onboardingSlides(),
            'appName' => AppExport::get('app_name'),
        ]);
    }
}
