<?php

namespace App\Services\Maintenance\Contracts;

use App\Models\ErrorLog;
use App\Services\Maintenance\ProposedFix;

/**
 * Turns a logged error into a proposed fix (blueprint Section 29). The
 * production implementation calls Claude; it is gated on configuration and
 * reports `available()` = false until the API key is set (never invents work).
 */
interface FixProposer
{
    public function available(): bool;

    public function propose(ErrorLog $error): ProposedFix;
}
