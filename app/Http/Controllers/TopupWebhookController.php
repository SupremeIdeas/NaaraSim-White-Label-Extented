<?php

namespace App\Http\Controllers;

use App\Jobs\Topup\ProcessTopupWebhookJob;
use App\Services\Topup\TopupProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /webhooks/topup/{provider}: verify (per supplier), queue, answer 200 fast. */
class TopupWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        abort_unless(isset(TopupProviders::MAP[$provider]), 404);
        $parsed = TopupProviders::get($provider)->parseWebhook($request);
        if (! $parsed || ! $parsed->reference) {
            return response()->json(['ok' => false], 401);
        }
        ProcessTopupWebhookJob::dispatch($provider, $parsed->reference, $parsed->status);

        return response()->json(['ok' => true]);
    }
}
