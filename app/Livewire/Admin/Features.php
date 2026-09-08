<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\FeatureFlags;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Features (owner request). One place to switch platform features on or
 * off, independently of whether their API keys are set — an extra control on top
 * of the key-driven "Coming Soon" strategy. Each feature shows whether its keys
 * are present and, where relevant, an in-panel setup guide (e.g. the VAPID keys
 * for web push).
 *
 * Re-authorized on every request via booted() — Livewire method calls hit the
 * shared update endpoint where the route's role middleware is not re-run.
 */
#[Layout('components.layouts.admin')]
class Features extends Component
{
    /** Which feature's setup guide is expanded. */
    public ?string $openGuide = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function toggle(string $key): void
    {
        if (! isset(FeatureFlags::FEATURES[$key])) {
            return;
        }
        $next = ! FeatureFlags::adminEnabled($key);
        FeatureFlags::setEnabled($key, $next);
        Auditor::log('feature.toggled', null, null, ['feature' => $key, 'enabled' => $next]);
    }

    public function showGuide(string $key): void
    {
        $this->openGuide = $this->openGuide === $key ? null : $key;
    }

    public function render()
    {
        return view('livewire.admin.features', [
            'features' => FeatureFlags::all(),
            'appUrl' => config('app.url'),
        ]);
    }
}
