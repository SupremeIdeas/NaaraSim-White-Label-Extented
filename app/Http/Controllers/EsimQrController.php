<?php

namespace App\Http\Controllers;

use App\Models\EsimOrder;
use App\Support\Niche\EsimQr;
use Illuminate\Http\Request;

/**
 * Renders an eSIM's activation QR as an SVG, generated from the LPA string so a
 * scannable code always exists even when the provider returned only the text
 * code (no image URL). Owner-scoped: the buyer, OR the V2 merchant who assigned
 * the eSIM to their client. Never exposes cost/provider — only the LPA the user
 * already needs to install.
 */
class EsimQrController extends Controller
{
    public function __invoke(Request $request, EsimOrder $order)
    {
        $user = $request->user();
        $owns = (int) $order->user_id === (int) $user->id;

        // A V2 merchant may render the QR for an eSIM assigned to their client.
        if (! $owns && $order->merchant_client_id) {
            $owns = $order->merchantClient?->merchant?->owner_user_id === $user->id;
        }
        abort_unless($owns, 404);

        $lpa = (string) $order->lpa_string;
        abort_unless($lpa !== '', 404);

        return response(EsimQr::svg($lpa), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
