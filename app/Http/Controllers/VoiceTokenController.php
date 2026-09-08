<?php

namespace App\Http\Controllers;

use App\Services\SMS\VoiceProviderInterface;
use App\Support\ProviderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Mints the short-lived WebRTC access token the in-browser dialer loads
 * (Live Voice — Part B). Auth-only, and 404s unless Twilio is Active (voice
 * rides the same keys — no new toggle). The token is scoped to THIS user's
 * identity and the outbound TwiML Application; it carries no wallet authority —
 * every charge still flows through VoiceDialerService + WalletService.
 */
class VoiceTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(ProviderStatus::isActive('twilio'), 404);

        $user = $request->user();

        // Cheap guard against token farming (tokens are short-lived anyway).
        $key = 'voice-token:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        $identity = self::identityFor($user->id);

        return response()->json([
            'token' => $voice->accessToken($identity),
            'identity' => $identity,
        ]);
    }

    /** Deterministic, URL-safe Twilio client identity for a user. */
    public static function identityFor(int $userId): string
    {
        return 'naara_user_'.$userId;
    }

    /** Resolve a user id back from a client identity (used by the dial webhook). */
    public static function userIdFrom(string $identity): ?int
    {
        if (preg_match('/^naara_user_(\d+)$/', $identity, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
