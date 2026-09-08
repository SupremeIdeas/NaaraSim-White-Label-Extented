<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\QuoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Sanctum-authenticated API (blueprint Section 3.1). Rate limited 300/min
// authenticated, 60/min public (Section 19.2). Every external call runs as a
// queued job, never synchronously in the request cycle.
Route::middleware(['throttle:api', 'auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

/*
 | Developer API — reselling surface (ROADMAP §Layer 2). OFF until the admin
 | enables `developer_api.enabled`. Authenticates an ApiClient (the Sanctum
 | tokenable), gates on the client being usable, and enforces per-route scopes
 | (the key's Sanctum abilities). Every price is the DEVELOPER lane price
 | (wholesale + admin markup, MarginGuard-floored) — cost is never exposed.
 */
Route::prefix('v1')
    ->middleware(['throttle:api', 'api.enabled', 'auth:sanctum', 'api.client'])
    ->group(function () {
        Route::get('catalogue', [CatalogueController::class, 'index'])->middleware('api.scope:catalogue');
        Route::post('quote', [QuoteController::class, 'store'])->middleware('api.scope:quote');
        Route::post('orders', [OrderController::class, 'store'])->middleware('api.scope:order');
        Route::get('orders/{reference}', [OrderController::class, 'show'])->middleware('api.scope:status');
    });
