<?php

namespace App\Services\Maintenance;

/**
 * A proposed fix returned by a FixProposer (blueprint Section 29): a title +
 * summary, the concrete file changes to apply (path => full new content), and a
 * human-readable unified diff for review.
 */
class ProposedFix
{
    /**
     * @param  array<string,string>  $changes  path => new file content
     */
    public function __construct(
        public string $title,
        public string $summary,
        public array $changes,
        public string $diff,
    ) {
    }

    /** @return list<string> the file paths this fix would change */
    public function targetFiles(): array
    {
        return array_keys($this->changes);
    }
}
