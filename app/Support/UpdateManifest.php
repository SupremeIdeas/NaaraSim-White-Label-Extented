<?php

namespace App\Support;

/**
 * A small typed wrapper around a decoded `manifest.json` from a `.naaraupdate`
 * package (blueprint Batch 1 §1.6). Downstream batches — the apply engine
 * (Batch 2), the distribution API (Batch 4), the white-label pull flow
 * (Batch 5) — read `$manifest->minCompatibleVersion` instead of juggling raw
 * array keys, which meaningfully reduces bugs in the code that consumes
 * packages. This is a pure value object: no I/O, no side effects.
 */
class UpdateManifest
{
    /** The canonical, sortable version scheme: YYYY.MM.DD-N. */
    public const VERSION_PATTERN = '/^\d{4}\.\d{2}\.\d{2}-\d+$/';

    public function __construct(
        public readonly string $packageId,
        public readonly string $product,
        public readonly string $version,
        public readonly string $minCompatibleVersion,
        public readonly string $packageType,
        public readonly string $builtAt,
        public readonly ?string $builtBy,
        public readonly ?string $sourceBranch,
        public readonly string $changelog,
        /** @var array<int,array{path:string,action:string,sha256:string,size_bytes:int}> */
        public readonly array $files,
        /** @var array<int,string> */
        public readonly array $migrations,
        /** @var array<int,string> */
        public readonly array $deletions,
        public readonly bool $requiresComposerInstall,
        public readonly bool $requiresNpmBuild,
        public readonly ?string $tierRequirement,
    ) {
    }

    /**
     * Build the manifest from a decoded array. Only the fields that must exist
     * for the package to be meaningful are treated as required; the rest carry
     * safe defaults so an older, thinner manifest still decodes rather than
     * throwing.
     *
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['package_id', 'product', 'version', 'min_compatible_version', 'package_type'] as $required) {
            if (! array_key_exists($required, $data) || $data[$required] === null || $data[$required] === '') {
                throw new \InvalidArgumentException("manifest.json is missing required field: {$required}");
            }
        }

        return new self(
            packageId: (string) $data['package_id'],
            product: (string) $data['product'],
            version: (string) $data['version'],
            minCompatibleVersion: (string) $data['min_compatible_version'],
            packageType: (string) $data['package_type'],
            builtAt: (string) ($data['built_at'] ?? ''),
            builtBy: isset($data['built_by']) ? (string) $data['built_by'] : null,
            sourceBranch: isset($data['source_branch']) ? (string) $data['source_branch'] : null,
            changelog: (string) ($data['changelog'] ?? ''),
            files: array_values($data['files'] ?? []),
            migrations: array_values($data['migrations'] ?? []),
            deletions: array_values($data['deletions'] ?? []),
            requiresComposerInstall: (bool) ($data['requires_composer_install'] ?? false),
            requiresNpmBuild: (bool) ($data['requires_npm_build'] ?? false),
            tierRequirement: isset($data['tier_requirement']) ? (string) $data['tier_requirement'] : null,
        );
    }

    /**
     * The exact array shape written to `manifest.json`. Key order is fixed so
     * the same input always serialises to the same bytes — the signature is
     * taken over these bytes, so stability matters.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'package_id' => $this->packageId,
            'product' => $this->product,
            'version' => $this->version,
            'min_compatible_version' => $this->minCompatibleVersion,
            'package_type' => $this->packageType,
            'built_at' => $this->builtAt,
            'built_by' => $this->builtBy,
            'source_branch' => $this->sourceBranch,
            'changelog' => $this->changelog,
            'files' => $this->files,
            'migrations' => $this->migrations,
            'deletions' => $this->deletions,
            'requires_composer_install' => $this->requiresComposerInstall,
            'requires_npm_build' => $this->requiresNpmBuild,
            'tier_requirement' => $this->tierRequirement,
        ];
    }

    /** Is a version string in the canonical YYYY.MM.DD-N form? */
    public static function isValidVersion(?string $version): bool
    {
        return is_string($version) && preg_match(self::VERSION_PATTERN, $version) === 1;
    }

    /**
     * Order two canonical version strings. Returns <0, 0, >0 like the spaceship
     * operator. Compares date parts numerically then the daily sequence number,
     * so "2026.09.01-2" correctly sorts after "2026.08.15-10".
     */
    public static function compareVersions(string $a, string $b): int
    {
        return self::versionSortKey($a) <=> self::versionSortKey($b);
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private static function versionSortKey(string $version): array
    {
        if (preg_match('/^(\d{4})\.(\d{2})\.(\d{2})-(\d+)$/', $version, $m) !== 1) {
            // Unparseable versions sort before every valid one rather than
            // throwing — callers that care about validity check isValidVersion.
            return [0, 0, 0, 0];
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]];
    }
}
