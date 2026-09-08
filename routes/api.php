<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\WhiteLabel\WhiteLabelThemeController;
use App\Http\Controllers\Api\V1\WhiteLabel\WhiteLabelUpdateController;
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

/*
 | White-label distribution API (Updater Batch 4). The publisher side, on the
 | master platform: registered white-label instances check for and download
 | signed .naaraupdate packages (code AND theme). OFF until the admin enables
 | `white_label_api.enabled`. Authenticates a WhiteLabelInstance (the Sanctum
 | tokenable), gates on the instance being active, enforces per-route scopes
 | (the token's Sanctum abilities), and logs every authenticated call centrally.
 | Downloads re-validate tier/version eligibility server-side; the receiving
 | instance still verifies the signature locally before applying.
 */
Route::prefix('v1/white-label')
    ->middleware(['throttle:api', 'whitelabel.enabled', 'auth:sanctum', 'whitelabel.usable'])
    ->name('white-label.')
    ->group(function () {
        Route::get('updates/check', [WhiteLabelUpdateController::class, 'check'])
            ->middleware('api.scope:updates.check')->name('updates.check');
        Route::get('updates/{package}/download', [WhiteLabelUpdateController::class, 'download'])
            ->middleware('api.scope:updates.download')->name('updates.download');
        // Batch 5 §4 — closes the oversight loop: reports what happened when a
        // downloaded package was actually applied. Reuses updates.check (no new
        // scope needed) and serves both code and theme outcome reports.
        Route::post('updates/report', [WhiteLabelUpdateController::class, 'report'])
            ->middleware('api.scope:updates.check')->name('updates.report');
        Route::get('themes/check', [WhiteLabelThemeController::class, 'check'])
            ->middleware('api.scope:themes.check')->name('themes.check');
        Route::get('themes/{package}/download', [WhiteLabelThemeController::class, 'download'])
            ->middleware('api.scope:themes.download')->name('themes.download');
    });
