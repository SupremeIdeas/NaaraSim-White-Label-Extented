<?php

namespace App\Http\Middleware;

use App\Support\BrandWordFilter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Puts the licensee's brand into every HTML page and Livewire update (see BrandWordFilter). */
class RebrandResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $response;
        }
        $type = (string) $response->headers->get('Content-Type', '');
        $body = $response->getContent();
        if (! is_string($body) || $body === '') {
            return $response;
        }

        if (str_contains($type, 'text/html')) {
            $response->setContent(BrandWordFilter::html($body));
        } elseif (str_contains($type, 'application/json') && $request->is('livewire/update') && (str_contains($body, 'Naara') || str_contains($body, 'Supreme Ideas'))) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                array_walk_recursive($json, function (&$v, $k) {
                    if ($k === 'html' && is_string($v)) {
                        $v = BrandWordFilter::html($v);
                    }
                });
                $response->setContent(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        return $response;
    }
}
