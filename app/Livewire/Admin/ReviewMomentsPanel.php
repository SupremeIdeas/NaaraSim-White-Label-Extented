<?php

namespace App\Livewire\Admin;

use App\Models\ReviewMoment;
use App\Support\Auditor;
use App\Support\Reviews\ReviewMoments;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Trustpilot > Review moments: where and when the app asks "how did it go?" after something really worked. Off until switched on and a Trustpilot profile is set. */
#[Layout('components.layouts.admin')]
class ReviewMomentsPanel extends Component
{
    public bool $enabled = false;

    public int $idleMinutes = 15;

    public int $cooldownDays = 14;

    public int $windowDays = 14;

    /** @var array<string, bool> */
    public array $on = [];

    /** @var array<string, int> */
    public array $delay = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->enabled = ReviewMoments::switchedOn();
        $this->idleMinutes = ReviewMoments::idleMinutes();
        $this->cooldownDays = ReviewMoments::cooldownDays();
        $this->windowDays = ReviewMoments::windowDays();
        foreach (array_keys(ReviewMoments::catalog()) as $k) {
            $this->on[$k] = ReviewMoments::momentOn($k);
            $this->delay[$k] = ReviewMoments::delayMinutes($k);
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['idleMinutes' => ['integer', 'min:1', 'max:240'], 'cooldownDays' => ['integer', 'min:0', 'max:365'], 'windowDays' => ['integer', 'min:1', 'max:90'], 'delay.*' => ['integer', 'min:0', 'max:10080']]);
        ReviewMoments::put('enabled', $this->enabled);
        ReviewMoments::put('idle_minutes', $this->idleMinutes);
        ReviewMoments::put('cooldown_days', $this->cooldownDays);
        ReviewMoments::put('window_days', $this->windowDays);
        foreach (array_keys(ReviewMoments::catalog()) as $k) {
            ReviewMoments::put($k.'.on', (bool) ($this->on[$k] ?? true));
            ReviewMoments::put($k.'.delay', (int) ($this->delay[$k] ?? 0));
        }
        Auditor::log('reviews.moments_saved', null, null, ['enabled' => $this->enabled, 'by' => Auth::id()]);
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    public function render()
    {
        $since = now()->subDays(30);
        $stats = ReviewMoment::query()->where('qualified_at', '>=', $since)->selectRaw("moment, count(*) qualified, sum(case when shown_at is not null then 1 else 0 end) asked, sum(case when outcome = 'rated' then 1 else 0 end) rated, sum(case when trustpilot_opened then 1 else 0 end) opened, avg(stars) avg_stars")->groupBy('moment')->get()->keyBy('moment');

        return view('livewire.admin.review-moments-panel', ['catalog' => ReviewMoments::catalog(), 'stats' => $stats, 'reviewUrl' => ReviewMoments::reviewUrl(), 'live' => ReviewMoments::enabled()]);
    }
}
