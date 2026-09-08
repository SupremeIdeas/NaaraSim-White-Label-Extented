<?php

namespace App\Services\Maintenance;

use App\Models\ErrorLog;
use App\Services\Maintenance\Contracts\FixProposer;
use Illuminate\Support\Facades\Http;

/**
 * Production FixProposer (blueprint Section 29): asks Claude to analyse a logged
 * error and return a concrete, minimal fix as JSON. Gated on
 * services.anthropic.api_key — reports unavailable until configured (never
 * invents a fix). The model is env-driven so it can be tuned without a deploy.
 */
class ClaudeFixProposer implements FixProposer
{
    public function available(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    public function propose(ErrorLog $error): ProposedFix
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 4096,
            'system' => $this->systemPrompt(),
            'messages' => [[
                'role' => 'user',
                'content' => $this->userPrompt($error),
            ]],
        ])->throw()->json();

        $text = $response['content'][0]['text'] ?? '{}';
        $data = json_decode($this->stripFences($text), true) ?: [];

        return new ProposedFix(
            title: $data['title'] ?? 'Proposed fix',
            summary: $data['summary'] ?? '',
            changes: $data['changes'] ?? [],
            diff: $data['diff'] ?? '',
        );
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a careful maintenance engineer for a Laravel application. Given a
        logged error, propose the SMALLEST safe code change that fixes the root
        cause. Never modify secrets, .env files, credentials or keys. Reply with
        ONLY a JSON object: {"title": string, "summary": string, "changes":
        {"relative/path.php": "FULL new file contents"}, "diff": "a unified diff
        for human review"}. Keep changes minimal and self-contained.
        PROMPT;
    }

    private function userPrompt(ErrorLog $error): string
    {
        return "Error code: {$error->code}\n"
            ."Severity: {$error->severity}\n"
            ."Message: {$error->message}\n\n"
            .'Context: '.json_encode($error->context ?? []);
    }

    /** Tolerate a model that wraps its JSON in ```json fences. */
    private function stripFences(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);

        return preg_replace('/\s*```$/', '', $text);
    }
}
