<?php

namespace App\Services\Blog;

use App\Models\Post;
use App\Services\AI\AnthropicClient;

/**
 * Claude-assisted blog authoring. Trained (via the system prompt) on NaaraSim's
 * real feature set, it scans the existing posts to pick the NEXT most useful
 * article, drafts SEO-tuned rich HTML for admin approval, proposes a cover-image
 * prompt (admin generates the image elsewhere + uploads), and reformats a draft
 * for clean, consistent reading. Lights up only when the Anthropic key is active
 * (money-safety rule 10); every method degrades gracefully otherwise. All model
 * HTML is allowlist-sanitized before it ever touches the DB.
 */
class BlogArticleAssistant
{
    public function __construct(private AnthropicClient $ai) {}

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    private function platformContext(): string
    {
        return <<<'TXT'
        NaaraSim is a Pan-African travel-connectivity SaaS. Tagline: "Stay Connected. No Borders. No Swaps."
        It uniquely sells BOTH data AND numbers in one app. Real, built features:
        - eSIM Data Plans for 190+ countries (instant activation, no SIM swap).
        - Naara Connect: Full eSIMs (calls + data).
        - Naara Verify: disposable numbers for OTP/SMS verification.
        - Naara Rent: rent virtual numbers (US long rentals + honest short-term elsewhere).
        - Naara Line: a permanent second number, voice + SMS (incl. MMS on US/CA).
        - Call Forwarding, in-browser Internet Calls (dialer), Contact Management.
        - Multi-currency wallet, NaaraCredits loyalty, a merchant/reseller program, a Developer API.
        Audience: African travellers, diaspora, remote workers, and global professionals.
        Never invent features that aren't listed. Never mention supplier/provider brand names.
        TXT;
    }

    /**
     * Pick the next most valuable article given what's already published.
     *
     * @return array{title:string, category:string, angle:string, rationale:string}
     */
    public function suggestTopic(): array
    {
        $recent = Post::latest('id')->limit(30)->get(['title', 'category']);
        $existing = $recent->map(fn ($p) => "- [{$p->category}] {$p->title}")->implode("\n") ?: '(no posts yet)';

        $system = $this->platformContext()."\n\nYou are NaaraSim's content strategist. Choose the single NEXT blog article that best fills a gap, avoids duplicating existing posts, and drives SEO + conversions. Respond ONLY as compact JSON.";

        $data = $this->ai->completeJson($system, [[
            'role' => 'user',
            'content' => "Existing posts:\n{$existing}\n\nReturn JSON: {\"title\": string, \"category\": string, \"angle\": string (1-2 sentences on what to cover + the target keyword), \"rationale\": string (why this next)}",
        ]], 1024);

        return [
            'title' => (string) ($data['title'] ?? 'Untitled'),
            'category' => (string) ($data['category'] ?? 'Guides'),
            'angle' => (string) ($data['angle'] ?? ''),
            'rationale' => (string) ($data['rationale'] ?? ''),
        ];
    }

    /**
     * Draft a full, SEO-tuned article for a topic.
     *
     * @return array{body:string, excerpt:string, meta_title:string, meta_description:string}
     */
    public function generateDraft(string $title, string $category, string $angle = ''): array
    {
        $system = $this->platformContext()."\n\n".$this->bodyFormatRules()."\n\nYou are an expert NaaraSim blog writer. Write accurate, engaging, SEO-optimised articles: scannable subheads, a clear intro + conclusion, natural keyword use. Respond ONLY as compact JSON.";

        $data = $this->ai->completeJson($system, [[
            'role' => 'user',
            'content' => "Write the article.\nTitle: {$title}\nCategory: {$category}\nAngle/keyword: {$angle}\n\nReturn JSON: {\"body\": string (600-1000 words in the required plain format), \"excerpt\": string (<=300 chars), \"meta_title\": string (<=60 chars), \"meta_description\": string (<=155 chars)}",
        ]], 4096);

        return [
            'body' => $this->cleanBody((string) ($data['body'] ?? '')),
            'excerpt' => mb_substr(trim((string) ($data['excerpt'] ?? '')), 0, 300),
            'meta_title' => mb_substr(trim((string) ($data['meta_title'] ?? $title)), 0, 160),
            'meta_description' => mb_substr(trim((string) ($data['meta_description'] ?? '')), 0, 300),
        ];
    }

    /** The blog renderer escapes HTML and supports only this light markup. */
    private function bodyFormatRules(): string
    {
        return "BODY FORMAT (STRICT): plain text only — NO HTML tags, NO markdown bold/italic/links. The ONLY supported markup is:\n- \"## Subheading\" on its own line for a section heading\n- \"- item\" on its own line for a bullet\n- a blank line between paragraphs\nAnything else renders literally, so do not use it.";
    }

    /** Strip any stray HTML the model may emit (body is rendered as escaped text). */
    private function cleanBody(string $body): string
    {
        $body = strip_tags($body);

        return trim(preg_replace("/\n{3,}/", "\n\n", $body));
    }

    /** A cover-image generation prompt tailored to the article (admin copies it). */
    public function imagePrompt(string $title, string $body): string
    {
        $system = 'You write vivid, specific prompts for a text-to-image model to create a premium blog COVER image. No text/words in the image. Brand palette: deep teal #0A6E6E, warm gold #D4A017, midnight navy #0D1B2A. 16:9. Return ONLY the prompt text, no preamble.';

        return trim($this->ai->complete($system, [[
            'role' => 'user',
            'content' => "Article title: {$title}\n\nExcerpt of body:\n".mb_substr(strip_tags($body), 0, 800),
        ]], 512));
    }

    /** Reformat an existing draft for clean, consistent, easy reading. */
    public function reformat(string $body): string
    {
        $system = $this->platformContext()."\n\n".$this->bodyFormatRules()."\n\nReformat the article for clean, consistent, easy reading: clear \"## \" subheads, tight paragraphs, bullets where helpful, no redundancy — WITHOUT changing the facts or adding new claims. Return ONLY the reformatted body in the required plain format.";

        $out = $this->ai->complete($system, [[
            'role' => 'user',
            'content' => $body,
        ]], 4096);

        return $this->cleanBody($out);
    }
}
