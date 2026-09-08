<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SmsException;
use App\Http\Controllers\Controller;
use App\Models\EsimPlan;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Developer API — live quote (ROADMAP §Layer 2). Returns the DEVELOPER price for
 * an eSIM plan or a number (otp/rental) without ordering. Cost is never exposed;
 * every price runs through the PricingEngine developer lane (MarginGuard-floored).
 */
class QuoteController extends Controller
{
    public function store(Request $request, PricingEngine $pricing, SmsNumberRouter $router): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:esim,number',
            'plan_id' => 'required_if:type,esim|integer',
            'number_type' => 'required_if:type,number|in:otp,rental',
            'country' => 'required_if:type,number|string',
            'service' => 'required_if:type,number|string',
        ]);

        if ($data['type'] === 'esim') {
            $plan = EsimPlan::where('is_active', true)->find($data['plan_id']);
            if (! $plan) {
                throw ValidationException::withMessages(['plan_id' => 'Unknown or inactive plan.']);
            }

            return response()->json([
                'type' => 'esim',
                'plan_id' => $plan->id,
                'price_usd' => round($pricing->developerEsimPrice($plan, log: false), 4),
                'currency' => 'USD',
            ]);
        }

        // number: quote live from the owning lane, then apply the developer lane
        // to the wholesale cost (never the retail markup, never expose cost).
        try {
            $quote = $router->quote(new NumberRequest(
                $data['country'],
                $data['number_type'],
                $data['service'],
                $request->user()->owner, // the developer, for lane context
            ));
        } catch (SmsException) {
            return response()->json([
                'error' => 'unavailable',
                'message' => 'No number is available for that country and service right now.',
            ], 422);
        }

        return response()->json([
            'type' => 'number',
            'number_type' => $data['number_type'],
            'country' => $data['country'],
            'service' => $data['service'],
            'price_usd' => round($pricing->developerSmsPrice((float) $quote['cost'], $quote['provider'], log: false), 4),
            'currency' => 'USD',
        ]);
    }
}
