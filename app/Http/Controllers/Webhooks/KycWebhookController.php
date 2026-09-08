<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * KYC result callbacks (ROADMAP §Layer 0.3) for Smile ID / Dojah. Verify the
 * signature BEFORE touching the payload, log it, then apply the decision to the
 * verification through KycService (idempotent). Manual review has no webhook.
 */
class KycWebhookController extends Controller
{
    private const PROVIDERS = ['smileid', 'dojah'];

    public function __invoke(Request $request, string $provider, KycService $kyc): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new NotFoundHttpException;
        }

        /** @var KycProviderInterface $svc */
        $svc = app("kyc.{$provider}");
        $verified = $svc->verifyWebhook($request);

        $log = WebhookLog::create([
            'provider' => 'kyc:'.$provider,
            'event_type' => $request->input('event') ?? $request->input('status'),
            'payload' => $request->all(),
            'signature' => $request->header('x-smileid-signature') ?? $request->header('x-dojah-signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $event = $svc->parseWebhook($request);
        if ($event !== null && $event->reference !== '') {
            $kyc->applyWebhook($event);
        }

        $log->update(['processed' => true, 'processed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
