<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourStep;
use App\Support\Auditor;
use App\Support\Tours\TourIntegrity;
use App\Support\Tours\TourRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Guided tours (Tour Studio): edit copy, order and enablement, publish a new version, reset to the code defaults, check every tour, and read privacy-safe completion/drop-off numbers. */
#[Layout('components.layouts.admin')]
class TourStudio extends Component
{
    public ?int $editing = null;

    public string $title = '';

    public string $summary = '';

    public bool $is_active = true;

    public bool $auto_start = true;

    public bool $quick_enabled = true;

    /** @var array<int, array{title:string, body:string, quick:bool, is_active:bool}> */
    public array $steps = [];

    public int $window_days = 30;

    public string $hub_after = 'hide';

    /** @var list<string>|null */
    public ?array $check = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->window_days = \App\Support\Tours\TourWindow::days();
        $this->hub_after = \App\Support\Tours\TourWindow::hubAfterWindow();
    }

    public function edit(int $id): void
    {
        $t = Tour::with('steps')->findOrFail($id);
        $this->editing = $t->id;
        [$this->title, $this->summary, $this->is_active, $this->auto_start, $this->quick_enabled] = [$t->title, (string) $t->summary, $t->is_active, $t->auto_start, $t->quick_enabled];
        $this->steps = $t->steps->mapWithKeys(fn ($s) => [$s->id => ['title' => $s->title, 'body' => $s->body, 'quick' => $s->quick, 'is_active' => $s->is_active]])->all();
    }

    public function save(): void
    {
        $this->guard();
        $this->validate([
            'title' => ['required', 'string', 'max:120'], 'summary' => ['nullable', 'string', 'max:200'],
            'steps.*.title' => ['required', 'string', 'max:120'], 'steps.*.body' => ['required', 'string', 'max:400'],
        ]);
        $t = Tour::findOrFail($this->editing);
        $t->update(['title' => trim($this->title), 'summary' => trim($this->summary) ?: null, 'is_active' => $this->is_active, 'auto_start' => $this->auto_start, 'quick_enabled' => $this->quick_enabled, 'is_customized' => true]);
        foreach ($this->steps as $id => $s) {
            TourStep::where('tour_id', $t->id)->whereKey($id)->update(['title' => trim($s['title']), 'body' => trim($s['body']), 'quick' => (bool) $s['quick'], 'is_active' => (bool) $s['is_active']]);
        }
        Auditor::log('tour.saved', Tour::class, $t->id);
        $this->dispatch('nx-toast', type: 'success', message: 'Tour saved.');
    }

    public function move(int $stepId, string $dir): void
    {
        $this->guard();
        $s = TourStep::findOrFail($stepId);
        $rows = TourStep::where('tour_id', $s->tour_id)->orderBy('position')->get()->values();
        $i = $rows->search(fn ($r) => $r->id === $s->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= $rows->count()) {
            return;
        }
        $ids = $rows->pluck('id')->all();
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $pos => $id) {
            TourStep::whereKey($id)->update(['position' => $pos + 1]);
        }
        Tour::whereKey($s->tour_id)->update(['is_customized' => true]);
        $this->edit($s->tour_id);
    }

    /** A new version re-offers the tour to people who already finished or dismissed it. */
    public function publish(): void
    {
        $this->guard();
        $t = Tour::findOrFail($this->editing);
        $t->update(['version' => $t->version + 1, 'is_customized' => true]);
        Auditor::log('tour.version_published', Tour::class, $t->id, ['version' => $t->version]);
        $this->dispatch('nx-toast', type: 'success', message: "Published version {$t->version}. People are offered it again.");
    }

    public function resetDefaults(): void
    {
        $this->guard();
        $t = Tour::findOrFail($this->editing);
        if (TourRegistry::reset($t->key)) {
            $this->edit($t->id);
            $this->dispatch('nx-toast', type: 'success', message: 'Reset to the default tour.');
        }
    }

    public function checkAll(): void
    {
        $this->guard();
        TourRegistry::sync();
        $this->check = TourIntegrity::problems();
    }

    public function saveWindow(): void
    {
        $this->guard();
        $this->validate(['window_days' => ['required', 'integer', 'min:0', 'max:3650'], 'hub_after' => ['required', 'in:hide,keep']]);
        Setting::setValue('tours.window_days', $this->window_days, 'tours');
        Setting::setValue('tours.hub_visibility_after_window', $this->hub_after, 'tours');
        Auditor::log('tour.window_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function render()
    {
        $since = now()->subDays(30)->toDateString();
        $ev = DB::table('tour_events')->where('day', '>=', $since)->select('tour_key', 'anchor', 'event', DB::raw('SUM(total) as n'))->groupBy('tour_key', 'anchor', 'event')->get();
        $tours = Tour::withCount('steps')->orderBy('sort')->get()->map(function ($t) use ($ev) {
            $mine = $ev->where('tour_key', $t->key);
            $started = (int) $mine->whereIn('event', ['started', 'replay'])->sum('n');
            $done = (int) $mine->where('event', 'completed')->sum('n');
            $t->stats = ['started' => $started, 'completed' => $done, 'rate' => $started > 0 ? round($done / $started * 100) : null, 'dismissed' => (int) $mine->where('event', 'dismissed')->sum('n')];
            $t->stepStats = $mine->whereIn('event', ['step_viewed', 'step_missing'])->groupBy('anchor')->map(fn ($g) => ['viewed' => (int) $g->where('event', 'step_viewed')->sum('n'), 'missing' => (int) $g->where('event', 'step_missing')->sum('n')]);

            return $t;
        });

        return view('livewire.admin.tour-studio', ['tours' => $tours, 'editingTour' => $this->editing ? Tour::with('steps')->find($this->editing) : null]);
    }
}
