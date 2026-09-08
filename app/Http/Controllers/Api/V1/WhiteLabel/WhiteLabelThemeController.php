<?php

namespace App\Http\Controllers\Api\V1\WhiteLabel;

use App\Http\Controllers\Controller;

/**
 * White-label distribution API — theme packages (Batch 4 §3.3). Structurally
 * identical to WhiteLabelUpdateController, serving the `theme` package family
 * (Batch 3's theme packages) instead of code.
 */
class WhiteLabelThemeController extends Controller
{
    use DistributesPackages;

    protected function family(): string
    {
        return 'theme';
    }
}
