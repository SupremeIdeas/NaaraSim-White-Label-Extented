<?php

namespace App\Livewire;

use App\Support\Niche\DataEstimator as Estimator;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Data estimator (blueprint Section 32) — helps a traveller size a plan before
 * buying. Pure UI over Support\Niche\DataEstimator; no cost/PII involved.
 */
#[Layout('components.layouts.customer')]
class DataEstimator extends Component
{
    public string $profile = 'medium';

    public int $days = 7;

    public function render()
    {
        $days = max(1, min(365, $this->days));
        $estimate = Estimator::estimate($this->profile, $days);

        return view('livewire.data-estimator', [
            'profiles' => Estimator::PROFILES,
            'estimate' => $estimate,
        ]);
    }
}
