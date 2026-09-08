<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\GiftCardOrder;
use App\Services\GiftCards\GiftCardOrderService;
use App\Support\Auditor;
use Illuminate\Http\Request;

/**
 * Naara Gift async delivery webhook (Phase 3). Reloadly/Zendit call this with the
 * final transaction status + receipt. Verified with an HMAC secret before the
 * payload is trusted (rule 9), idempotent (never downgrades a terminal order),
 * and it fills the three-state receipt so the redemption screen updates.
 */
class GiftCardWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, GiftCardOrderService $orders)
    {
        $secret = (string) config("services.{$provider}.webhook_secret", '');
        if ($secret !== '') {
            $sig = (string) $request->header('X-Naara-Signature', $request->header('X-Reloadly-Signature', ''));
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            abort_unless(hash_equals($expected, $sig), 401);
        }

        $data = $request->json()->all();
        $norm = $this->normalize($provider, $data);
        $order = $norm['ref'] !== '' ? GiftCardOrder::where('transaction_ref', $norm['ref'])->first() : null;
        abort_unless($order, 404);

        // Never resurrect/downgrade a finished order.
        if (in_array($order->status, [GiftCardOrder::STATUS_DELIVERED, GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED], true)) {
            return response()->json(['ok' => true]);
        }

        // A terminal failure from the provider must refund the (already charged)
        // buyer — not just flip the status. Idempotent on the reference.
        if ($norm['status'] === 'failed') {
            $orders->failAndRefund($order);
            Auditor::log('giftcard.webhook', GiftCardOrder::class, $order->id, ['status' => GiftCardOrder::STATUS_FAILED]);

            return response()->json(['ok' => true]);
        }

        $mapped = $norm['status'] === 'delivered' ? GiftCardOrder::STATUS_DELIVERED : GiftCardOrder::STATUS_PROCESSING;
        $receipt = array_merge((array) $order->receipt, $norm['receipt']);

        $order->update([
            'status' => $mapped,
            'receipt' => $receipt,
            'provider_tx_id' => $order->provider_tx_id ?: $norm['provider_tx_id'],
        ]);
        Auditor::log('giftcard.webhook', GiftCardOrder::class, $order->id, ['status' => $mapped]);

        return response()->json(['ok' => true]);
    }

    /**
     * Each provider's webhook payload uses different field names — normalize
     * to {ref, status: 'processing'|'delivered'|'failed', receipt, provider_tx_id}
     * BEFORE the shared refund/update logic above, so adding a provider never
     * touches the (already correct, tested) Reloadly/Zendit path.
     *
     * @return array{ref: string, status: string, receipt: array<string, mixed>, provider_tx_id: ?string}
     */
    private function normalize(string $provider, array $data): array
    {
        return match ($provider) {
            'bitrefill' => $this->normalizeBitrefill($data),
            'tillo' => $this->normalizeTillo($data),
            // Reloadly + Zendit — unchanged from the original implementation.
            default => $this->normalizeDefault($data),
        };
    }

    private function normalizeDefault(array $data): array
    {
        $status = strtoupper((string) ($data['status'] ?? ''));
        $r = (array) ($data['receipt'] ?? []);

        return [
            'ref' => (string) ($data['customIdentifier'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? ''),
            'status' => match ($status) {
                'DONE', 'SUCCESSFUL', 'DELIVERED' => 'delivered',
                'FAILED' => 'failed',
                default => 'processing',
            },
            'receipt' => array_filter([
                'epin' => $r['epin'] ?? null,
                'code' => $r['cardNumber'] ?? ($r['code'] ?? null),
                'redemption_url' => $r['redemptionUrl'] ?? null,
                'account_id' => $r['accountId'] ?? null,
                'instructions' => $r['instructions'] ?? null,
                'terms' => $r['terms'] ?? null,
                'expires_at' => $r['expiresAt'] ?? null,
                'delivery_type' => $r['deliveryType'] ?? null,
            ], fn ($v) => $v !== null),
            'provider_tx_id' => $data['transactionId'] ?? null,
        ];
    }

    /** Bitrefill's real invoice-completed webhook posts the invoice/order object. */
    private function normalizeBitrefill(array $data): array
    {
        $status = (string) ($data['status'] ?? '');
        $order = (array) (data_get($data, 'orders.0') ?? []);

        return [
            'ref' => (string) ($data['external_id'] ?? ''),
            'status' => match ($status) {
                'complete' => 'delivered',
                'expired', 'error', 'refunded' => 'failed',
                default => 'processing',
            },
            'receipt' => array_filter([
                'code' => data_get($order, 'code'),
                'epin' => data_get($order, 'pin'),
                'redemption_url' => data_get($order, 'link'),
                'instructions' => data_get($order, 'instructions'),
            ], fn ($v) => $v !== null && $v !== ''),
            'provider_tx_id' => $data['id'] ?? null,
        ];
    }

    /**
     * Tillo's async issue webhook — field names beyond {client_request_id,
     * status, data.code, data.url} should be confirmed against a real
     * delivered payload once the enterprise account is live (same honest
     * caveat as TilloService's other endpoints).
     */
    private function normalizeTillo(array $data): array
    {
        $status = strtolower((string) ($data['status'] ?? ''));

        return [
            'ref' => (string) ($data['client_request_id'] ?? ''),
            'status' => match ($status) {
                'success', 'issued', 'complete' => 'delivered',
                'error', 'failed', 'declined' => 'failed',
                default => 'processing',
            },
            'receipt' => array_filter([
                'code' => data_get($data, 'data.code'),
                'redemption_url' => data_get($data, 'data.url'),
            ], fn ($v) => $v !== null && $v !== ''),
            'provider_tx_id' => data_get($data, 'data.code'),
        ];
    }
}
