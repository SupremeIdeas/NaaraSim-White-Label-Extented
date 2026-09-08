<?php

namespace App\Services\Maintenance\Contracts;

use App\Services\Maintenance\ProposedFix;

/**
 * Opens/reverts pull requests on the code host (blueprint Section 29). The
 * production implementation talks to GitHub with a fine-grained token scoped to
 * this repo only. A fix never lands straight in production — it always goes
 * through a CI-gated PR that a human merges.
 */
interface CodeHostClient
{
    public function available(): bool;

    /**
     * Create a branch, apply the fix's file changes, and open a (draft) PR.
     *
     * @return array{branch:string,pr_url:string}
     */
    public function openPullRequest(ProposedFix $fix): array;

    /**
     * Roll back a previously opened PR — close it if unmerged, or open a revert
     * PR if it was merged.
     */
    public function rollback(string $branch, string $prUrl): void;
}
