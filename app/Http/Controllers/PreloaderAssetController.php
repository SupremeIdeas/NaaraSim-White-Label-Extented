<?php

namespace App\Http\Controllers;

use App\Support\CustomPreloader;
use Symfony\Component\HttpFoundation\Response;

/** Serves an admin-uploaded Lottie preloader from our own origin (the CSP only lets scripts fetch from 'self'). Public: a preloader shows on sign-in pages. */
class PreloaderAssetController extends Controller
{
    public function __invoke(string $id): Response
    {
        $json = CustomPreloader::lottieContents($id);
        abort_if($json === null, 404);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
