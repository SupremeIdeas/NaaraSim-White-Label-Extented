<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\EsimProviderException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Http\Controllers\Controller;
use App\Jobs\AlertAdminJob;
use App\Jobs\PollSmsOtpJob;
use App\Models\ApiClient;
use App\Models\ApiOrder;
use App\Models\ApiWalletTransaction;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Services\Api\ApiWalletService;
use App\Services\eSIM\ProviderRouter;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use App\Support\Niche\LpaActivation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Developer API — orders (ROADMAP §Layer 2). Bills the client's PREPAID API
 * wallet (never a user wallet) at the developer price, fulfils via the shared
 * provider routers, and returns a masked order (no cost, no supplier). Money-safe
 * like the storefront: charge-first, idempotent per (client, reference), refund
 * on failure, and an orphan-charge guard if persistence fails.
 */
class OrderController extends Controller
{
    public function store(
        Request $request,
        PricingEngine $pricing,
        ProviderRouter $esim,
        SmsNumberRouter $sms,
        ApiWalletService $wallet,
    ): JsonResponse {
        $data = $request->validate([
            'type' => 'required|in:esim,number',
            'plan_id' => 'required_if:type,esim|integer',
            'number_type' => 'required_if:type,number|in:otp,rental',
            'country' => 'required_if:type,number|string',
            'service' => 'required_if:type,number|string',
            'reference' => 'nullable|string|max:64', // developer idempotency key
        ]);

        /** @var ApiClient $client */
        $client = $request->user();
        $ref = ($data['reference'] ?? null) ?: (string) Str::uuid();

        // Idempotency at the order level: a retried reference never provisions or
        // charges twice — return the order we already made.
        $existing = ApiOrder::where('api_client_id', $client->id)->where('reference', $ref)->first();
        if ($existing) {
            return response()->json($existing->toApiArray(), 200);
        }

        return $data['type'] === 'esim'
            ? $this->orderEsim($client, $ref, (int) $data['plan_id'], $pricing, $esim, $wallet)
            : $this->orderNumber($client, $ref, $data, $pricing, $sms, $wallet);
    }

    private function orderEsim(ApiClient $client, string $ref, int $planId, PricingEngine $pricing, ProviderRouter $router, ApiWalletService $wallet): JsonResponse
    {
        $plan = EsimPlan::where('is_active', true)->find($planId);
        if (! $plan) {
            return response()->json(['error' => 'invalid_plan', 'message' => 'Unknown or inactive plan.'], 422);
        }

        $price = round($pricing->developerEsimPrice($plan), 4);

        $debit = $this->charge($wallet, $client, $price, $ref, "eSIM: {$plan->name}");
        if ($debit instanceof JsonResponse) {
            return $debit;
        }
        if (! $debit->wasRecentlyCreated) {
            return $this->duplicateInProgress($client, $ref);
        }

        try {
            $result = $router->fulfil($plan, $price, $client->owner);
        } catch (EsimProviderException) {
            return $this->refundAnd502($wallet, $client, $price, $ref);
        }

        try {
            $esim = EsimOrder::create([
                'user_id' => $client->owner_user_id,
                'plan_id' => $plan->id,
                'provider' => $result->provider,
                'provider_order_ref' => data_get($result->payload, 'orderReference') ?? data_get($result->payload, 'id'),
                'iccid' => data_get($result->payload, 'iccid') ?? data_get($result->payload, 'esims.0.iccid'),
                'bundle_name' => $result->providerPlanId,
                'qr_code_url' => data_get($result->payload, 'qr_code') ?? data_get($result->payload, 'qrCodeUrl'),
                'lpa_string' => LpaActivation::fromPayload($result->payload),
                'status' => 'processing',
                'price_charged' => $price,
                'wholesale_cost' => $result->cost,
                'currency' => 'USD',
            ]);

            $order = ApiOrder::create([
                'api_client_id' => $client->id,
                'kind' => 'esim',
                'reference' => $ref,
                'status' => 'processing',
                'price_usd' => $price,
                'currency' => 'USD',
                'esim_order_id' => $esim->id,
                'result' => ['iccid' => $esim->iccid, 'qr_code' => $esim->qr_code_url, 'lpa' => $esim->lpa_string],
            ]);
        } catch (Throwable $e) {
            return $this->orphanRefund($wallet, $client, $price, $ref, $plan->id);
        }

        return response()->json($order->toApiArray(), 201);
    }

    private function orderNumber(ApiClient $client, string $ref, array $data, PricingEngine $pricing, SmsNumberRouter $router, ApiWalletService $wallet): JsonResponse
    {
        $req = fn (?float $charged = null) => new NumberRequest(
            $data['country'], $data['number_type'], $data['service'], $client->owner, 'USD', $charged,
        );

        // Live quote (provider + wholesale cost) → developer price.
        try {
            $quote = $router->quote($req());
        } catch (SmsException) {
            return response()->json(['error' => 'unavailable', 'message' => 'No number available for that country and service right now.'], 422);
        }
        $price = round($pricing->developerSmsPrice((float) $quote['cost'], $quote['provider']), 4);

        $debit = $this->charge($wallet, $client, $price, $ref, "Number: {$data['service']}");
        if ($debit instanceof JsonResponse) {
            return $debit;
        }
        if (! $debit->wasRecentlyCreated) {
            return $this->duplicateInProgress($client, $ref);
        }

        // Fulfil with the developer price as the margin ceiling; no user wallet.
        try {
            $result = $router->attempt($req($price));
        } catch (SmsException) {
            return $this->refundAnd502($wallet, $client, $price, $ref);
        }

        try {
            PollSmsOtpJob::dispatch($result->order->id, 'USD'); // fetch the code in the background
            $order = ApiOrder::create([
                'api_client_id' => $client->id,
                'kind' => 'number',
                'reference' => $ref,
                'status' => 'processing',
                'price_usd' => $price,
                'currency' => 'USD',
                'sms_order_id' => $result->order->id,
                'result' => ['number' => $result->order->phone_number, 'code' => null],
            ]);
        } catch (Throwable $e) {
            return $this->orphanRefund($wallet, $client, $price, $ref, null);
        }

        return response()->json($order->toApiArray(), 201);
    }

    /** Status of a previously placed order (scoped to the calling client). */
    public function show(Request $request, string $reference): JsonResponse
    {
        $order = ApiOrder::where('api_client_id', $request->user()->id)
            ->where('reference', $reference)
            ->first();

        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Reflect the latest fulfilment state from the linked order (live).
        if ($order->esim_order_id && ($status = $order->esimStatus())) {
            $order->status = $status;
        } elseif ($order->sms_order_id) {
            $order->applyNumberStatus();
        }

        return response()->json($order->toApiArray());
    }

    // ---- money-safety helpers ----------------------------------------------

    /**
     * Charge the API wallet. Returns a 402 JsonResponse if the balance is short,
     * otherwise the debit transaction — whose wasRecentlyCreated flag lets the
     * caller tell a fresh charge from an idempotent replay (the upfront ApiOrder
     * check is read-then-act, so two concurrent calls with the same reference can
     * both reach here; the debit is idempotent but fulfilment is not).
     */
    private function charge(ApiWalletService $wallet, ApiClient $client, float $price, string $ref, string $desc): JsonResponse|ApiWalletTransaction
    {
        try {
            return $wallet->debit($client, $price, ['reference' => "api-order:{$ref}", 'description' => $desc]);
        } catch (InsufficientBalanceException) {
            return response()->json(['error' => 'insufficient_balance', 'message' => 'Top up your API balance and retry.'], 402);
        }
    }

    /**
     * A concurrent request with the same reference already charged for this order
     * — never fulfil again (that would double the provider cost). Return the
     * order if the sibling request has persisted it, else signal in-progress.
     */
    private function duplicateInProgress(ApiClient $client, string $ref): JsonResponse
    {
        $existing = ApiOrder::where('api_client_id', $client->id)->where('reference', $ref)->first();
        if ($existing) {
            return response()->json($existing->toApiArray(), 200);
        }

        return response()->json([
            'error' => 'duplicate_in_progress',
            'message' => 'An order with this reference is already being processed.',
        ], 409);
    }

    private function refundAnd502(ApiWalletService $wallet, ApiClient $client, float $price, string $ref): JsonResponse
    {
        $wallet->refund($client, $price, ['reference' => "refund:api-order:{$ref}", 'description' => 'Order not fulfilled']);

        return response()->json(['error' => 'unfulfilled', 'message' => 'No provider could fulfil this right now. Your API balance was refunded.'], 502);
    }

    private function orphanRefund(ApiWalletService $wallet, ApiClient $client, float $price, string $ref, ?int $planId): JsonResponse
    {
        $wallet->refund($client, $price, ['reference' => "refund:api-order:{$ref}", 'description' => 'Order could not be saved']);
        AlertAdminJob::dispatch(
            code: 'api_order_save_failed',
            message: "API order for client {$client->id} fulfilled but failed to persist; API balance refunded.",
            context: ['api_client_id' => $client->id, 'plan_id' => $planId, 'reference' => $ref],
        );

        return response()->json(['error' => 'order_failed', 'message' => 'Something went wrong finalising the order. Your API balance was refunded.'], 500);
    }
}
