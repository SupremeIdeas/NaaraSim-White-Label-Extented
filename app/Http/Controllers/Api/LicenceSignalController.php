<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshLicenceJob;
use App\Support\Activation\LicenseState;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * "Your licence changed, check in now." The original platform calls this after an upgrade, suspension, restore or revocation so the install does not wait for its
 * next scheduled check-in. It trusts NOTHING in the request: it only queues a normal signed check-in, which asks the platform (over the token this install already
 * holds) what the licence includes and believes only a signed answer. So a forged call can do no more than cause one check-in, and calls are throttled and debounced.
 */
class LicenceSignalController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (! LicenseState::isActivated()) {
            return response()->json(['ok' => false], 404);
        }
        if (Cache::add('licence-signal:debounce', 1, 30)) {
            RefreshLicenceJob::dispatch();
        }

        return response()->json(['ok' => true], 202);
    }
}
