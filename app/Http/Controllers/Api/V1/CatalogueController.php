<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EsimPlan;
use App\Services\Pricing\PricingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Developer API — eSIM catalogue (ROADMAP §Layer 2). Lists active plans at the
 * DEVELOPER price (wholesale + admin markup, MarginGuard-floored). Provider cost
 * and the real supplier are never exposed — the developer sees a stable plan id,
 * its attributes, and their price.
 */
class CatalogueController extends Controller
{
    public function index(Request $request, PricingEngine $pricing): JsonResponse
    {
        $plans = EsimPlan::query()
            ->where('is_active', true)
            // Optional filter: ?has_voice=true returns only Naara Connect (Full
            // eSIM: calls + data) plans; ?has_voice=false only data-only plans.
            ->when($request->has('has_voice'), fn ($q) => $q->where('has_voice', $request->boolean('has_voice')))
            ->orderBy('name')
            ->get()
            ->map(fn (EsimPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'type' => $plan->type,
                'has_voice' => (bool) $plan->has_voice,
                'data_mb' => $plan->data_mb,
                'validity_days' => $plan->validity_days,
                'countries' => $plan->countries,
                'price_usd' => round($pricing->developerEsimPrice($plan, log: false), 4),
                'currency' => 'USD',
            ]);

        return response()->json(['data' => $plans]);
    }
}
