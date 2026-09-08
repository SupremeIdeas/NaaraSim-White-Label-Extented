<?php

namespace App\Services\Maintenance;

use App\Services\Maintenance\Contracts\CodeHostClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Production CodeHostClient (blueprint Section 29): opens CI-gated pull requests
 * on GitHub using a fine-grained token scoped to THIS repo only. A fix is never
 * committed to the default branch — it lands on a new branch behind a draft PR
 * that a human merges once CI is green. Gated on configuration.
 */
class GitHubCodeHostClient implements CodeHostClient
{
    public function available(): bool
    {
        return filled($this->token()) && filled($this->repo());
    }

    public function openPullRequest(ProposedFix $fix): array
    {
        $base = $this->baseBranch();
        $branch = 'claude/maint-'.now()->format('Ymd-His').'-'.Str::random(4);

        // Branch off the current default-branch head.
        $headSha = $this->api('get', "/git/ref/heads/{$base}")['object']['sha'];
        $this->api('post', '/git/refs', ['ref' => "refs/heads/{$branch}", 'sha' => $headSha]);

        // Apply each file change on the new branch.
        foreach ($fix->changes as $path => $contents) {
            $existing = $this->apiOrNull('get', '/contents/'.$path."?ref={$branch}");
            $this->api('put', '/contents/'.$path, array_filter([
                'message' => 'Maintenance: '.$fix->title,
                'content' => base64_encode($contents),
                'branch' => $branch,
                'sha' => $existing['sha'] ?? null,
            ], fn ($v) => $v !== null));
        }

        $pr = $this->api('post', '/pulls', [
            'title' => 'Maintenance: '.$fix->title,
            'head' => $branch,
            'base' => $base,
            'body' => $fix->summary."\n\n_Proposed by the NaaraSim maintenance loop. Merges only after CI passes._",
            'draft' => true,
        ]);

        return ['branch' => $branch, 'pr_url' => $pr['html_url']];
    }

    public function rollback(string $branch, string $prUrl): void
    {
        // Close the PR (unmerged fixes never reached prod) and delete the branch.
        if (preg_match('#/pull/(\d+)$#', $prUrl, $m)) {
            $this->api('patch', '/pulls/'.$m[1], ['state' => 'closed']);
        }
        $this->apiOrNull('delete', "/git/refs/heads/{$branch}");
    }

    private function api(string $method, string $path, array $body = []): array
    {
        return Http::withToken($this->token())
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->{$method}('https://api.github.com/repos/'.$this->repo().$path, $body)
            ->throw()
            ->json() ?? [];
    }

    private function apiOrNull(string $method, string $path, array $body = []): ?array
    {
        try {
            return $this->api($method, $path, $body);
        } catch (\Throwable) {
            return null;
        }
    }

    private function token(): ?string
    {
        return config('services.github_maintenance.token');
    }

    private function repo(): ?string
    {
        return config('services.github_maintenance.repo');
    }

    private function baseBranch(): string
    {
        return config('services.github_maintenance.base_branch', 'main');
    }
}
