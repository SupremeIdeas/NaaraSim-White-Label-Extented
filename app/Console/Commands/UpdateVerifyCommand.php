<?php

namespace App\Console\Commands;

use App\Services\Updater\PackageVerifier;
use Illuminate\Console\Command;

/**
 * Standalone verification of a `.naaraupdate` package (blueprint Batch 1 §1.5),
 * with no side effects. Checks the signature, then every payload file's
 * checksum against the manifest, and prints a pass/fail report. This is the
 * command an admin — and, later, Batch 2's apply engine and Batch 5's download
 * flow (which call the same PackageVerifier directly) — rely on before
 * considering applying anything.
 *
 * Exit 0 on a full pass, non-zero with a specific reason on any failure, so it
 * is scriptable in CI.
 */
class UpdateVerifyCommand extends Command
{
    protected $signature = 'update:verify {path : Path to the .naaraupdate file}';

    protected $description = 'Verify a NaaraSim update package is genuine and untampered';

    public function handle(PackageVerifier $verifier): int
    {
        $path = $this->argument('path');
        $result = $verifier->verify($path);

        if (! $result->passed) {
            $this->error('REJECTED: '.$result->reason);

            return self::FAILURE;
        }

        $m = $result->manifest;
        $this->info('VERIFIED — package is genuine and untampered.');
        $this->newLine();
        $this->line("  Product:            {$m->product}");
        $this->line("  Version:            {$m->version}");
        $this->line("  Min compatible:     {$m->minCompatibleVersion}");
        $this->line("  Package type:       {$m->packageType}");
        $this->line('  Files:              '.count($m->files));
        $this->line('  Migrations:         '.count($m->migrations));
        $this->line('  Deletions:          '.count($m->deletions));
        $this->line('  Needs composer:     '.($m->requiresComposerInstall ? 'yes' : 'no'));
        $this->line('  Needs npm build:    '.($m->requiresNpmBuild ? 'yes' : 'no'));
        $this->line('  Tier requirement:   '.($m->tierRequirement ?? '(none — core update)'));
        if ($m->changelog !== '') {
            $this->newLine();
            $this->line('  Changelog: '.$m->changelog);
        }

        return self::SUCCESS;
    }
}
