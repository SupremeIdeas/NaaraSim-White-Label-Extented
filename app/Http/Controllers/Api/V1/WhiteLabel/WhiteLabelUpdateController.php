<?php

namespace App\Http\Controllers\Api\V1\WhiteLabel;

use App\Http\Controllers\Controller;

/**
 * White-label distribution API — code update packages (Batch 4 §3.1/§3.2).
 * Serves the `code`/`code_and_migrations`/`migrations` package family. All
 * check/download logic lives in DistributesPackages; this controller only names
 * the family it serves.
 */
class WhiteLabelUpdateController extends Controller
{
    use DistributesPackages;

    protected function family(): string
    {
        return 'code';
    }
}
