<?php

namespace App\Livewire;

use App\Models\TourProgress;
use App\Support\Tours\TourProgressService;
use App\Support\Tours\TourRegistry;
use App\Support\Tours\TourWindow;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Account > Guided tours: replay any tour (full or quick), resume one, switch automatic tips on/off, reset. Hidden after the new-user window unless the owner chose "keep". */
#[Layout('components.layouts.customer')]
class GuidedTours extends Component
{
    public bool $auto = true;

    public function mount(): void
    {
        abort_unless(TourWindow::hubVisible(Auth::user()), 404);
        $this->auto = TourWindow::autoEnabled(Auth::user());
    }

    public function updatedAuto(bool $on): void
    {
        TourWindow::setAuto(Auth::user(), $on);
        $this->dispatch('nx-toast', type: 'success', message: $on ? 'Tips will show up automatically.' : 'Automatic tips are off. You can still replay any tour here.');
    }

    public function resetAll(): void
    {
        TourProgressService::resetAll(Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'All tours reset.');
    }

    /** Where to send the person so the engine starts the tour (it reads ?tour= on the entry page). */
    public function start(string $key, string $mode = 'full', bool $resume = false): void
    {
        $tour = TourRegistry::eligible(Auth::user())->firstWhere('key', $key);
        if (! $tour) {
            return;
        }
        $this->redirect(route($tour->entry_route, ['tour' => $key, 'mode' => $mode === 'quick' ? 'quick' : 'full'] + ($resume ? ['resume' => 1] : [])), navigate: false);
    }

    public function render()
    {
        $u = Auth::user();
        $progress = TourProgress::where('user_id', $u->id)->get()->keyBy('tour_key');
        $tours = TourRegistry::eligible($u)->map(function ($t) use ($progress) {
            $p = $progress->get($t->key);
            $current = $p && $p->tour_version >= $t->version;
            $total = $t->steps()->where('is_active', true)->count();

            return [
                'tour' => $t, 'status' => $current ? $p->status : 'not_started', 'step' => $current ? (int) $p->last_step : 0, 'total' => $total,
                'quick' => $t->quick_enabled ? $t->steps()->where('is_active', true)->where('quick', true)->count() : 0, 'at' => $current ? $p->completed_at : null,
            ];
        });

        return view('livewire.guided-tours', ['tours' => $tours, 'open' => TourWindow::open($u), 'days' => TourWindow::days()]);
    }
}
