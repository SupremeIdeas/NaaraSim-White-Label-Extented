<?php

namespace App\Support;

/**
 * Output-side guardrail for the NaaraCare AI agent (Module 24). SupportGuard
 * stops private cost/secret data flowing IN to the model; this stops the
 * model's own free-text reply claiming a money/account action it didn't
 * actually take. A tool schema can't catch a plain-English hallucination
 * like "I've refunded you $10" — the model can say that without ever
 * calling grant_goodwill_credit — so this is a dedicated check on the way
 * OUT, applied to the final reply text before it reaches the user.
 */
class SupportReplyGuard
{
    private const MAX_REPLY_LENGTH = 4000;

    /** Reply language claiming a money or account action was completed. */
    private const UNVERIFIABLE_CLAIM_PATTERNS = [
        '/\bi(?:\'ve| have)? (?:just )?(?:refunded|credited|reimbursed)\b/i',
        '/\byour (?:refund|credit|goodwill|reimbursement) (?:has been|is|was) (?:applied|processed|issued|added)\b/i',
        '/\bi(?:\'ve| have)? (?:cancel(?:l)?ed|clos(?:ed|ing)|delet(?:ed|ing)) your account\b/i',
        '/\bi(?:\'ve| have)? changed your (?:email|password)\b/i',
    ];

    private const SAFE_FALLBACK = "I want to make sure that's actually been applied before telling you it's done — let me get a human to confirm and follow up with you shortly.";

    /**
     * Sanitize a reply before it reaches the customer. When the reply claims
     * a money/account action completed but no matching tool call actually
     * succeeded this turn, the claim is replaced with a safe hand-off
     * message rather than shown as-is.
     */
    public static function sanitize(string $reply, bool $moneyOrAccountActionSucceeded): string
    {
        $reply = mb_substr(trim($reply), 0, self::MAX_REPLY_LENGTH);

        if (! $moneyOrAccountActionSucceeded && self::claimsUnverifiedAction($reply)) {
            return self::SAFE_FALLBACK;
        }

        return $reply;
    }

    private static function claimsUnverifiedAction(string $reply): bool
    {
        foreach (self::UNVERIFIABLE_CLAIM_PATTERNS as $pattern) {
            if (preg_match($pattern, $reply) === 1) {
                return true;
            }
        }

        return false;
    }
}
