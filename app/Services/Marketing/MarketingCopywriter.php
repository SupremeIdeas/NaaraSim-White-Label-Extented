<?php

namespace App\Services\Marketing;

use App\Services\AI\AnthropicClient;
use App\Support\CopyFields;
use App\Support\MarketingBrief;

/**
 * Turns a page-section (or a whole page's worth of sections) into several fresh
 * copy variations in the brand's own voice, using Claude. It only ever sends and
 * receives the human-readable COPY (via CopyFields) — never a URL, image or
 * structural key — so an applied variation can change words but never break a
 * link or a layout.
 *
 * Admin-triggered and synchronous by design: the admin is actively waiting on the
 * result to review it, so there is nothing to background. It is not a
 * customer-request path and touches no money, so the money-safety "every external
 * call is a queued job" rule (which guards the request cycle) does not apply.
 */
class MarketingCopywriter
{
    public function __construct(private AnthropicClient $ai) {}

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * N copy variations for one section. Each returned item is a full config blob
     * (structure preserved) with the copy rewritten. Returns [] when the section
     * has no copy to rewrite.
     *
     * @return list<array<string,mixed>>
     */
    public function sectionVariations(string $type, array $config, int $n = 3): array
    {
        $copy = CopyFields::extract($config);
        if ($copy === []) {
            return [];
        }

        $system = $this->systemPrompt();
        $user = "Section type: {$type}\n"
            ."Rewrite the COPY below into {$n} distinct variations, in the brand voice. "
            ."Keep each field roughly its current length and purpose (a headline stays a headline, a CTA stays 1–3 words). "
            ."Do NOT add or remove keys. Return STRICT JSON of the shape "
            .'{"variations":[{"<path>":"<new text>", ...}, ...]} with exactly '."{$n} variations, "
            ."each object using the SAME keys as the input.\n\n"
            ."COPY (JSON path => current text):\n".json_encode($copy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $data = $this->ai->completeJson($system, [['role' => 'user', 'content' => $user]], 3000);
        $variations = $data['variations'] ?? [];
        if (! is_array($variations)) {
            return [];
        }

        $out = [];
        foreach ($variations as $vary) {
            if (! is_array($vary)) {
                continue;
            }
            // Keep only known paths, then splice onto a clone of the original.
            $clean = array_intersect_key($vary, $copy);
            $out[] = CopyFields::apply($config, $clean);
        }

        return $out;
    }

    private function systemPrompt(): string
    {
        return "You are a senior brand copywriter producing marketing website copy.\n\n"
            .MarketingBrief::forPrompt()."\n\n"
            ."Rules:\n"
            ."- Write in the brand's voice; use the brand name where natural, never a competitor's.\n"
            ."- Punchy, concrete, benefit-led. No clichés, no emoji, no markdown.\n"
            ."- Respect each field's role and length; never invent links, prices, or claims.\n"
            ."- Output ONLY the requested JSON, nothing else.";
    }
}
