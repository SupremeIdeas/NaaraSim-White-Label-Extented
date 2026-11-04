<?php

namespace App\Support;

use App\Support\Activation\LicenseState;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * What a person sees at a page their licence does not (yet) include.
 *
 * A browser page load gets a friendly sheet that says why and what to do (the site owner activates a licence that includes the feature); everything that is not a
 * page load (Livewire updates, JSON, webhooks, POSTs, images) keeps the plain 404, so a locked feature is still absent for machines and nothing is hinted at there.
 * The sheet is a normal 403-free 200-less answer: status 403, noindex, no feature internals.
 */
final class LockedFeature
{
    public static function abortIf(string $key): void
    {
        if (FeatureEntitlements::locked($key)) {
            self::deny($key);
        }
    }

    public static function deny(string $key): never
    {
        $r = request();
        $pageLoad = $r->isMethod('GET') && ! $r->expectsJson() && ! $r->headers->has('X-Livewire') && ! $r->is('livewire/*');
        if (! $pageLoad) {
            abort(404);
        }

        $label = FeatureLocks::catalog()[$key] ?? 'This feature';
        $u = $r->user();
        $isAdmin = $u && method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['super_admin', 'admin']);
        $activated = LicenseState::isActivated();

        if ($isAdmin) {
            $message = $activated
                ? $label.' is not part of the licence this site is running on. Upgrade the licence (or pay the outstanding balance on it) and this page opens by itself.'
                : $label.' opens once this site is activated with a licence that includes it. Enter the licence key you received to unlock it.';
            $url = url('/'.AdminPath::current().'/license');
            $action = $activated ? 'Check my licence' : 'Activate my licence';
        } else {
            $message = $label.' is not switched on for this site yet. The site owner needs to activate a licence that includes it; it will appear here as soon as they do.';
            $url = url('/');
            $action = 'Back to home';
        }

        throw new HttpResponseException(response()->view('errors.locked-feature', [
            'code' => '403', 'icon' => 'lock', 'badge' => 'Licence required',
            'title' => $label.' is locked', 'message' => $message,
            'actionUrl' => $url, 'actionLabel' => $action, 'back' => ! $isAdmin,
        ], 403));
    }
}
