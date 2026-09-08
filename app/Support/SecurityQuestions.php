<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Security-question recovery (owner request). Preset questions + the hashing and
 * verification rules. Answers are normalised (trim + lowercase) before hashing
 * so matching isn't case- or whitespace-sensitive, and stored as password-grade
 * hashes — never plaintext.
 */
class SecurityQuestions
{
    /** How many Q&A pairs a user configures / must answer. */
    public const REQUIRED = 3;

    /** @var list<string> */
    public const PRESETS = [
        'What was the name of your first pet?',
        'In what city were you born?',
        'What was the make of your first phone?',
        'What is your mother’s maiden name?',
        'What was the name of your first school?',
        'What is your favourite book?',
        'What street did you grow up on?',
        'What was your childhood nickname?',
    ];

    /** Normalise an answer before hashing/verifying (case- + space-insensitive). */
    public static function normalise(string $answer): string
    {
        return mb_strtolower(trim($answer));
    }

    /**
     * Build the stored payload from question => answer pairs.
     *
     * @param  array<string, string>  $pairs
     * @return list<array{question: string, answer_hash: string}>
     */
    public static function build(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $question => $answer) {
            $question = trim($question);
            $answer = self::normalise($answer);
            if ($question === '' || $answer === '') {
                continue;
            }
            $out[] = ['question' => $question, 'answer_hash' => Hash::make($answer)];
        }

        return $out;
    }

    /** Whether the user has security questions configured. */
    public static function configured(User $user): bool
    {
        return is_array($user->security_questions) && count($user->security_questions) >= self::REQUIRED;
    }

    /** The stored questions (text only) for a user, to prompt them. */
    public static function questionsFor(User $user): array
    {
        return collect($user->security_questions ?? [])->pluck('question')->all();
    }

    /**
     * Verify submitted answers (question text => answer). Every configured
     * question must be answered correctly.
     *
     * @param  array<string, string>  $answers
     */
    public static function verify(User $user, array $answers): bool
    {
        if (! self::configured($user)) {
            return false;
        }

        foreach ($user->security_questions as $entry) {
            $submitted = $answers[$entry['question']] ?? null;
            if ($submitted === null || ! Hash::check(self::normalise((string) $submitted), $entry['answer_hash'])) {
                return false;
            }
        }

        return true;
    }
}
