<?php

namespace App\Http\Controllers;

use App\Models\GiftCardOrder;
use App\Services\GiftCards\GiftCardBalanceCheckable;
use App\Services\GiftCards\GiftCardCatalogueSyncService;
use App\Support\FeatureFlags;
use Illuminate\Http\Request;
use Throwable;

/**
 * Naara Gift order history + the three-state redemption screen (code / link /
 * account), owner-scoped. Feature-gated with the storefront.
 */
class GiftCardOrderController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(FeatureFlags::enabled('naara_gift'), 404);

        $orders = GiftCardOrder::where('user_id', $request->user()->id)
            ->latest()->paginate(15);

        return view('gift-cards.orders', compact('orders'));
    }

    public function show(Request $request, GiftCardOrder $order, GiftCardCatalogueSyncService $sync)
    {
        abort_unless(FeatureFlags::enabled('naara_gift'), 404);
        abort_unless($order->user_id === $request->user()->id, 404);

        // "Check balance" is a REAL capability only where the provider
        // actually exposes an issued-card balance lookup (Tillo, confirmed) —
        // never shown for a card type that doesn't support it.
        $canCheckBalance = $order->isDelivered()
            && $sync->provider($order->provider) instanceof GiftCardBalanceCheckable;

        return view('gift-cards.receipt', ['order' => $order, 'canCheckBalance' => $canCheckBalance]);
    }

    /**
     * Live balance check against the provider — never cached, since the whole
     * point is showing the CURRENT remaining value after partial spend.
     */
    public function balance(Request $request, GiftCardOrder $order, GiftCardCatalogueSyncService $sync)
    {
        abort_unless(FeatureFlags::enabled('naara_gift'), 404);
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->isDelivered(), 422);

        $provider = $sync->provider($order->provider);
        abort_unless($provider instanceof GiftCardBalanceCheckable, 422);

        try {
            return response()->json($provider->checkBalance((array) $order->receipt));
        } catch (Throwable $e) {
            return response()->json(['error' => 'Could not reach the balance check right now.'], 502);
        }
    }
}
