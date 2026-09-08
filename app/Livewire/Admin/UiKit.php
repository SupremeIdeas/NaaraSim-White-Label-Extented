<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → UI Kit (blueprint Section 31). A living style guide for the reusable,
 * themed, accessible components: star rating, the one modal engine, theme
 * toggle, server-anchored countdown, and debounced search. Super-admin only —
 * it's developer/documentation surface, not an operational page.
 */
#[Layout('components.layouts.admin')]
class UiKit extends Component
{
    /** Bound to the interactive star-rating demo. */
    public int $demoRating = 4;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
    }

    public function render()
    {
        return view('livewire.admin.ui-kit', [
            'countdownUntil' => now()->addMinutes(3)->toIso8601String(),
        ]);
    }
}
