<?php

namespace App\Console\Commands;

use App\Services\Updater\PackageBuilder;
use App\Services\Updater\PackageVerifier;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Build and sign a `.naaraupdate` package from the changes since a git ref
 * (blueprint Batch 1 §1.5). Resolves the changed-file list with
 * `git diff --name-status`, hands it to PackageBuilder, and prints a
 * sanity-check summary BEFORE writing the artifact.
 *
 * The private signing key is supplied at build time via `--key=<file>` or the
 * NAARA_UPDATE_PRIVATE_KEY env var — never read from config or the repo.
 */
class UpdatePackageCommand extends Command
{
    protected $signature = 'update:package
        {--from= : Git ref to diff against (default: latest tag, else HEAD~1)}
        {--key= : Path to a file containing the base64 Ed25519 private signing key}
        {--type= : Override package_type (code, migrations, code_and_migrations, theme)}
        {--min-compatible= : min_compatible_version this package requires (YYYY.MM.DD-N)}
        {--changelog= : Human-readable changelog line}
        {--by= : Who built this (email); defaults to git user.email}
        {--tier= : Tier requirement key (default: none — a core update)}
        {--product= : Product line this package is built FOR (default: this instance's own config(\'updater.product_identifier\')). Master builds naarasim-core packages for itself by default — pass e.g. --product=naarasim-whitelabel to build a package intended for a white-label fork instead of for master itself.}
        {--requires-composer : Flag that applying needs composer install}
        {--requires-npm : Flag that applying needs an npm build}
        {--publish : After building, register + publish it to the distribution API (Batch 4)}
        {--master-only : Mark this package as never distributable to white label — enforced server-side (Naara Pro / master-only distribution lock), not just a UI hint. A build-time-only decision, not adjustable later from a toggle.}
        {--dry-run : Resolve and print the file list without writing a package}';

    protected $description = 'Build and sign a NaaraSim update package (.naaraupdate)';

    public function handle(PackageBuilder $builder, PackageVerifier $verifier, \App\Services\Updater\PackagePublisher $publisher): int
    {
        $base = base_path();
        $ref = $this->option('from') ?: $this->defaultRef($base);
        if ($ref === null) {
            $this->error('Could not determine a git ref to diff against. Pass --from=<ref>.');

            return self::FAILURE;
        }

        [$files, $deletions] = $this->resolveChanges($base, $ref);
        if ($files === [] && $deletions === []) {
            $this->warn("No changes found between {$ref} and the working tree — nothing to package.");

            return self::FAILURE;
        }

        $privateKey = $this->resolvePrivateKey();
        if ($privateKey === null && ! $this->option('dry-run')) {
            $this->error('No private signing key. Pass --key=<file> or set NAARA_UPDATE_PRIVATE_KEY.');

            return self::FAILURE;
        }

        $migrationCount = count(array_filter($files, fn ($f) => str_starts_with($f['path'], 'database/migrations/')));

        $this->info('Update package summary');
        $this->line('  Diff against ref:  '.$ref);
        $this->line('  Content files:     '.count($files)." (of which {$migrationCount} migrations)");
        $this->line('  Deletions:         '.count($deletions));
        $product = $this->option('product') ?: config('updater.product_identifier');
        $this->line('  Product:           '.$product);
        $this->line('  Distribution:      '.($this->option('master-only') ? 'MASTER-ONLY (can never be published to white label)' : 'distributable'));

        if ($this->option('dry-run')) {
            foreach ($files as $f) {
                $this->line('    '.$f['action'].'  '.$f['path']);
            }
            foreach ($deletions as $d) {
                $this->line('    delete  '.$d);
            }
            $this->comment('Dry run — no package written.');

            return self::SUCCESS;
        }

        $path = $builder->build([
            'files' => $files,
            'deletions' => $deletions,
            'private_key' => $privateKey,
            'product' => $product,
            'package_type' => $this->option('type') ?: null,
            'min_compatible_version' => $this->option('min-compatible') ?: null,
            'changelog' => $this->option('changelog') ?: '',
            'built_by' => $this->option('by') ?: $this->gitEmail($base),
            'source_branch' => $this->currentBranch($base),
            'tier_requirement' => $this->option('tier') ?: null,
            'requires_composer_install' => (bool) $this->option('requires-composer'),
            'requires_npm_build' => (bool) $this->option('requires-npm'),
            'distribution_scope' => $this->option('master-only')
                ? \App\Support\UpdateManifest::SCOPE_MASTER_ONLY
                : \App\Support\UpdateManifest::SCOPE_DISTRIBUTABLE,
        ]);

        // Immediately verify what we just built, so the command can never hand
        // back a package that wouldn't pass the very check applying it will run.
        $result = $verifier->verify($path, $product);
        if (! $result->passed) {
            $this->error('Built package failed self-verification: '.$result->reason);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Wrote and self-verified: '.$path);
        $this->line('  Version: '.$result->manifest->version);

        if ($this->option('publish')) {
            if ($this->option('master-only')) {
                // register() itself also forces is_published=false for a
                // master-only package — this pre-check exists so the CLI
                // output tells the truth about what actually happened rather
                // than looking like --publish succeeded.
                $this->warn('This package is --master-only — registering it, but NOT publishing (master-only packages can never be distributed).');
                $row = $publisher->register($path, publish: true);
                $this->info('Registered (master-only, unpublished) as package '.$row->package_id.'.');
            } else {
                $row = $publisher->register($path, publish: true);
                $this->info('Published to distribution as package '.$row->package_id.' (product: '.$row->product.').');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Turn `git diff --name-status <ref>` into builder input. Added/modified
     * files become content entries sourced from the current working tree;
     * deleted files become deletions; a rename is a delete of the old path plus
     * an add of the new one.
     *
     * @return array{0:array<int,array<string,string>>,1:array<int,string>}
     */
    private function resolveChanges(string $base, string $ref): array
    {
        $out = $this->git($base, ['diff', '--name-status', '--no-renames', $ref, '--', '.']);
        if ($out === null) {
            return [[], []];
        }

        $files = [];
        $deletions = [];

        foreach (preg_split('/\R/', trim($out)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\t/', $line);
            $status = $parts[0][0] ?? '';
            $path = $parts[count($parts) - 1] ?? '';
            if ($path === '') {
                continue;
            }

            if ($status === 'D') {
                $deletions[] = $path;
            } elseif (in_array($status, ['A', 'M', 'C', 'T'], true)) {
                $files[] = [
                    'path' => $path,
                    'action' => $status === 'A' ? 'add' : 'modify',
                    'source' => $base.'/'.$path,
                ];
            }
        }

        return [$files, $deletions];
    }

    private function defaultRef(string $base): ?string
    {
        $tag = $this->git($base, ['describe', '--tags', '--abbrev=0']);
        if ($tag !== null && trim($tag) !== '') {
            return trim($tag);
        }

        $head = $this->git($base, ['rev-parse', '--verify', '--quiet', 'HEAD~1']);

        return ($head !== null && trim($head) !== '') ? 'HEAD~1' : null;
    }

    private function currentBranch(string $base): ?string
    {
        $branch = $this->git($base, ['rev-parse', '--abbrev-ref', 'HEAD']);

        return $branch !== null ? trim($branch) : null;
    }

    private function gitEmail(string $base): ?string
    {
        $email = $this->git($base, ['config', 'user.email']);

        return ($email !== null && trim($email) !== '') ? trim($email) : null;
    }

    private function resolvePrivateKey(): ?string
    {
        if ($keyFile = $this->option('key')) {
            if (! is_file($keyFile)) {
                $this->error("Key file not found: {$keyFile}");

                return null;
            }

            return trim((string) file_get_contents($keyFile));
        }

        $env = env('NAARA_UPDATE_PRIVATE_KEY');

        return $env ? trim((string) $env) : null;
    }

    /** Run a git subcommand, returning stdout or null if git failed/absent. */
    private function git(string $base, array $args): ?string
    {
        try {
            $process = new Process(array_merge(['git'], $args), $base);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
