<?php

namespace App\Http\Controllers;

use App\Models\ReviewMoment;
use App\Support\Reviews\ReviewMoments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The "how did it go?" sheet's endpoints. Every row is the signed-in person's own; anything else is a plain 404. */
class ReviewMomentController extends Controller
{
    public function next(Request $request): JsonResponse
    {
        $m = ReviewMoments::next($request->user());

        return response()->json($m ? ['show' => true] + $m : ['show' => false], 200, ['Cache-Control' => 'no-store']);
    }

    public function rate(Request $request, int $id): JsonResponse
    {
        $stars = (int) $request->validate(['stars' => ['required', 'integer', 'min:1', 'max:5']])['stars'];
        $m = $this->mine($request, $id);
        abort_unless($m->shown_at !== null && $m->outcome === null && ReviewMoments::reviewUrl() !== null, 422);

        return response()->json(['ok' => true] + ReviewMoments::rate($m, $stars));
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $m = $this->mine($request, $id);
        if ($m->outcome === null) {
            $m->forceFill(['outcome' => 'dismissed'])->save();
        }

        return response()->json(['ok' => true]);
    }

    public function opened(Request $request, int $id): JsonResponse
    {
        $this->mine($request, $id)->forceFill(['trustpilot_opened' => true])->save();

        return response()->json(['ok' => true]);
    }

    private function mine(Request $request, int $id): ReviewMoment
    {
        return ReviewMoment::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
