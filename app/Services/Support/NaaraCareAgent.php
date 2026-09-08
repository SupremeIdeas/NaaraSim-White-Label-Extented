<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\Contracts\ChatModel;
use App\Support\SupportReplyGuard;
use App\Support\SupportSettings;

/**
 * NaaraCare — the AI support agent (Module 24). Orchestrates a bounded tool-use
 * loop against the ChatModel: the model can call the SupportTools (all scoped to
 * THIS user), see the user's real situation, and reply with a human-toned answer
 * + optionally a navigation shortcut, or escalate to a human. It never sees
 * another user's data, cost/profit, or any secret (SupportTools + SupportGuard).
 * Its own reply text is screened by SupportReplyGuard before it reaches the
 * user (readiness-audit fix, 2026-09-07) — the model claiming a refund/
 * account change it never actually performed is caught on the way out.
 */
class NaaraCareAgent
{
    /** Hard cap on tool round-trips per turn, so a loop can't run away. */
    private const MAX_STEPS = 6;

    public function __construct(private ChatModel $model) {}

    public function available(): bool
    {
        return $this->model->available();
    }

    /**
     * Answer one user turn within a conversation.
     *
     * @param  array<string, mixed>|null  $attachment  An Anthropic image/document
     *   content block for evidence the user attached this turn (built by
     *   SupportAttachment::toContentBlock). The model SEES it and diagnoses from it.
     * @return array{reply: string, nav: ?string, escalated: bool}
     */
    public function respond(User $user, SupportConversation $conversation, string $userMessage, ?array $attachment = null): array
    {
        $tools = new SupportTools($user, $conversation);
        $schemas = $tools->schemas();
        $system = $this->systemPrompt($user);

        $messages = $this->history($conversation);
        // Multimodal turn: when the user attached evidence, send the text and the
        // image/PDF together as content blocks so the model can analyse the file.
        if ($attachment !== null) {
            $blocks = [];
            if (trim($userMessage) !== '') {
                $blocks[] = ['type' => 'text', 'text' => $userMessage];
            }
            $blocks[] = $attachment;
            $messages[] = ['role' => 'user', 'content' => $blocks];
        } else {
            $messages[] = ['role' => 'user', 'content' => $userMessage];
        }

        $nav = null;
        $escalated = false;
        $moneyOrAccountActionSucceeded = false;

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $response = $this->model->reply($system, $messages, $schemas);
            $content = $response['content'] ?? [];
            $messages[] = ['role' => 'assistant', 'content' => $content];

            if (($response['stop_reason'] ?? 'end_turn') !== 'tool_use') {
                $raw = $this->extractText($content);
                $reply = SupportReplyGuard::sanitize($raw, $moneyOrAccountActionSucceeded);

                return [
                    'reply' => $reply,
                    'nav' => $nav,
                    // The guard rewriting the reply means the model claimed an
                    // unverified money/account action — hand it to a human too.
                    'escalated' => $escalated || $conversation->escalated || $reply !== $raw,
                ];
            }

            // Run every tool the model asked for and feed the results back.
            $toolResults = [];
            foreach ($content as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }
                $result = $tools->execute($block['name'], $block['input'] ?? []);

                if ($block['name'] === 'suggest_navigation' && ! empty($result['url'])) {
                    $nav = $result['url'];
                }
                if ($block['name'] === 'escalate_to_human') {
                    $escalated = true;
                }
                if ($block['name'] === 'grant_goodwill_credit' && ($result['done'] ?? false) === true) {
                    $moneyOrAccountActionSucceeded = true;
                }

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'] ?? '',
                    'content' => json_encode($result),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        // Ran out of steps — return whatever text we have plus a safe fallback.
        return [
            'reply' => "I'm having trouble resolving that automatically. Let me connect you with a human who can help.",
            'nav' => $nav,
            'escalated' => true,
        ];
    }

    private function systemPrompt(User $user): string
    {
        $name = SupportSettings::name();
        $persona = SupportSettings::persona();
        $knowledge = SupportSettings::knowledge();

        $prompt = <<<PROMPT
        You are {$name}, the customer support specialist for NaaraSim — a Pan-African
        travel-connectivity service that sells eSIM data plans for 190+ countries and
        virtual/verification phone numbers. Brand promise: "Stay Connected. No Borders. No Swaps."

        {$persona}

        HOW YOU HELP:
        - Your job is to solve each customer's SPECIFIC problem. Use your tools to look at
          THEIR actual orders, device, numbers and balance before answering — diagnose, then
          give a concrete solution or the exact next step.
        - When the customer attaches EVIDENCE (a screenshot, photo, or PDF), read it carefully
          and use what it shows to diagnose — quote the specific error, code, or detail you see.
        - Always run check_device_compatibility when eSIM device support is in question.
        - Use suggest_navigation to give them a shortcut to the right page.

        RESOLVING ON AUTOPILOT (act, don't just advise):
        - You may FIX common issues yourself with your action tools: refresh_number_code
          (re-fetch a stuck verification code), resend_esim_setup (re-send setup for a lost QR),
          grant_goodwill_credit (a SMALL, admin-capped goodwill gesture for a genuine minor
          inconvenience), and resolve_ticket (once the issue is truly fixed).
        - Prefer fixing the problem over handing it off, when it is one of these safe actions.
        - Never PROMISE a goodwill amount up front. Call grant_goodwill_credit, then tell the
          user only what actually applied. If it reports it could not apply, do not invent one —
          escalate.

        WHAT YOU MUST ESCALATE (never do these — you have no tool for them, by design):
        - Refunds or any cash back to the wallet; changing prices; anything about money beyond
          the small goodwill lane.
        - Account changes (email, password, closing/reactivating an account, deleting data).
        - Provider outages, or anything affecting someone other than this customer.
        - When in doubt, or the user is upset or explicitly asks for a person: escalate_to_human
          with a clear summary. Escalating is always the safe choice.

        STRICT RULES:
        - You can ONLY see this signed-in customer's own data. Never claim to see anyone else's.
        - NEVER mention or reveal internal costs, wholesale prices, profit, margins, provider
          economics, staff details, or any API key or secret — you do not have them.
        - Be honest when you don't know; never invent order numbers, prices or policies.

        The customer's name is: {$user->name}.
        PROMPT;

        if (trim($knowledge) !== '') {
            $prompt .= "\n\nPLATFORM KNOWLEDGE (authoritative — prefer this):\n".trim($knowledge);
        }

        return $prompt;
    }

    /**
     * Recent transcript as plain-text turns for context (tool round-trips from
     * past turns are not replayed — only the visible messages).
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function history(SupportConversation $conversation): array
    {
        return $conversation->messages()
            ->latest()->limit(10)->get()->reverse()
            ->map(fn ($m) => ['role' => $m->role === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m->body])
            ->values()->all();
    }

    /** Concatenate the text blocks of an assistant response. */
    private function extractText(array $content): string
    {
        $text = collect($content)
            ->filter(fn ($b) => ($b['type'] ?? null) === 'text')
            ->map(fn ($b) => $b['text'] ?? '')
            ->implode("\n");

        return trim($text) !== '' ? trim($text) : "I'm here to help — could you tell me a little more about what you need?";
    }
}
