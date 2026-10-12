<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Support\Tours\TourProgressService;
use App\Support\Tours\TourRegistry;
use App\Support\Tours\TourWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON for the browser tour engine. Every call is scoped to the signed-in person; a tour they are not eligible for answers 404. */
class TourController extends Controller
{
    /** The tour to offer on this page (the engine still applies the polite-behaviour rules before showing anything). */
    public function auto(Request $request): JsonResponse
    {
        $route = (string) $request->query('route', '');
        $t = $route !== '' ? TourProgressService::candidate($request->user(), $route) : null;

        return response()->json(['tour' => $t ? TourRegistry::payload($t, 'full', app()->getLocale()) : null, 'prompt' => $t?->summary]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $t = $this->tour($request, $key);
        $mode = $request->query('mode') === 'quick' && $t->quick_enabled ? 'quick' : 'full';
        // The hub and replays are available while the window is open (or always when the owner chose "keep").
        abort_unless(TourWindow::hubVisible($request->user()) || $request->boolean('auto'), 404);

        $payload = TourRegistry::payload($t, $mode, app()->getLocale());
        $p = TourProgressService::for($request->user(), $t);
        $payload['resume_at'] = ($request->boolean('resume') && $p && $p->status === 'in_progress' && $p->tour_version === $t->version && $p->last_mode === $mode) ? (int) $p->last_step : 0;

        return response()->json($payload);
    }

    public function progress(Request $request, string $key): JsonResponse
    {
        $t = $this->tour($request, $key);
        $d = $request->validate([
            'event' => ['required', 'in:started,step,skipped_step,completed,skipped,dismissed,missing'],
            'step' => ['nullable', 'integer', 'min:0', 'max:50'], 'anchor' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9._-]*$/'],
            'mode' => ['nullable', 'in:full,quick'], 'never' => ['nullable', 'boolean'],
        ]);
        $u = $request->user();
        match ($d['event']) {
            'started' => TourProgressService::start($u, $t, (string) ($d['mode'] ?? 'full')),
            'step' => TourProgressService::step($u, $t, (int) ($d['step'] ?? 0), (string) ($d['anchor'] ?? '')),
            'skipped_step' => TourProgressService::step($u, $t, (int) ($d['step'] ?? 0), (string) ($d['anchor'] ?? ''), true),
            'missing' => TourProgressService::missing($t, (string) ($d['anchor'] ?? '')),
            default => TourProgressService::finish($u, $t, $d['event']),
        };
        if (! empty($d['never'])) {
            TourWindow::setAuto($u, false);
        }

        return response()->json(['ok' => true]);
    }

    private function tour(Request $request, string $key): Tour
    {
        $t = Tour::where('key', $key)->where('is_active', true)->first();
        abort_unless($t && TourRegistry::audienceMatches($t, $request->user()) && \Illuminate\Support\Facades\Route::has($t->entry_route), 404);

        return $t;
    }
}
