<?php

namespace App\Services\Updater;

use App\Support\UpdateManifest;

/**
 * The outcome of verifying a `.naaraupdate` package. Deliberately a plain value
 * object so every consumer — the `update:verify` command, Batch 2's apply
 * engine, Batch 5's download flow — reads the same pass/fail signal and reason,
 * rather than each catching exceptions in its own way.
 */
class VerificationResult
{
    private function __construct(
        public readonly bool $passed,
        public readonly string $reason,
        public readonly ?UpdateManifest $manifest,
    ) {
    }

    public static function ok(UpdateManifest $manifest): self
    {
        return new self(true, 'Package is genuine and every file matches its checksum.', $manifest);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason, null);
    }
}
