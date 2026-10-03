<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Appearance\AppearanceException;
use App\Support\Appearance\UpdateUserAppearance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The header day/night toggles remember their choice on the member's ACCOUNT (not just this browser). Same write path as the
 * Appearance page, so validation, throttling and the operator lock all apply.
 */
class AppearanceModeController extends Controller
{
    public function __invoke(Request $request, UpdateUserAppearance $update): JsonResponse
    {
        $data = $request->validate(['mode' => 'required|in:light,dark']);
        try {
            $r = $update($request->user(), ['mode' => $data['mode']]);
        } catch (AppearanceException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'mode' => $r['mode']]);
    }
}
