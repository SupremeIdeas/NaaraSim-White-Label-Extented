<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Localization Phase A: resolves the request's locale (see App\Support\Locale)
 * and applies it for the whole request, then shares the text direction so
 * layouts can set <html dir="..."> once Arabic (RTL) actually ships.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::resolve($request->user());

        App::setLocale($locale);
        View::share('htmlDir', Locale::isRtl($locale) ? 'rtl' : 'ltr');

        return $next($request);
    }
}
