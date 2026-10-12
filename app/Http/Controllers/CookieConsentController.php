<?php

namespace App\Http\Controllers;

use App\Support\CookieConsent\CookieConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Records a visitor's cookie choice as proof of consent. Public and cookie-less by design: the visitor's browser holds the choice, this only keeps the evidence. */
class CookieConsentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,40}$/'],
            'action' => ['required', 'in:accept_all,reject_all,custom,withdraw,gpc'],
            'functional' => ['sometimes', 'boolean'], 'analytics' => ['sometimes', 'boolean'], 'marketing' => ['sometimes', 'boolean'],
            'path' => ['sometimes', 'nullable', 'string', 'max:160'],
        ]);
        // The action decides the choices where it can, so a tampered body cannot claim "accept_all" with everything off or the other way round.
        $choices = match ($data['action']) {
            'accept_all' => ['functional' => true, 'analytics' => true, 'marketing' => true],
            'reject_all', 'withdraw', 'gpc' => ['functional' => false, 'analytics' => false, 'marketing' => false],
            default => ['functional' => (bool) ($data['functional'] ?? false), 'analytics' => (bool) ($data['analytics'] ?? false), 'marketing' => (bool) ($data['marketing'] ?? false)],
        };
        CookieConsent::record($data['id'], $data['action'], $choices, $request->user()?->id, $request->ip(), $request->userAgent(), isset($data['path']) ? '/'.ltrim((string) parse_url((string) $data['path'], PHP_URL_PATH), '/') : null);

        return response()->json(['ok' => true, 'version' => CookieConsent::version()]);
    }

    /** The full cookie and consent page: every category and service in plain words, with the visitor's current choice and controls. */
    public function preferences()
    {
        return view('cookies.preferences', [
            'design' => CookieConsent::design(), 'categories' => (array) config('cookie_consent.categories'), 'inUse' => CookieConsent::categoriesInUse(), 'services' => CookieConsent::services(), 'version' => CookieConsent::version(),
        ]);
    }
}
