<?php

namespace App\Services\Chatbot;

/**
 * Scores a question against a set of keywords.
 *
 * Injected into every intent rather than inherited from a base class: an intent
 * *has a* matcher, it is not *a kind of* matcher. That keeps the scoring rule in
 * one place while leaving the intents as flat, independent classes.
 */
class KeywordMatcher
{
    /** Words carrying no topical meaning, dropped before matching. */
    private const STOP_WORDS = [
        'a', 'am', 'an', 'and', 'are', 'as', 'at', 'be', 'can', 'do', 'does',
        'for', 'get', 'has', 'have', 'how', 'i', 'if', 'in', 'is', 'it', 'me',
        'my', 'of', 'on', 'or', 'the', 'to', 'what', 'when', 'where', 'which',
        'why', 'will', 'with', 'you', 'your',
    ];

    /**
     * Reduces a question to comparable lowercase words.
     *
     * @return string[]
     */
    public function tokenise(string $question): array
    {
        $lower = mb_strtolower(trim($question));
        $words = preg_split('/[^a-z0-9]+/', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_diff($words, self::STOP_WORDS));
    }

    /**
     * Fraction of the intent's keywords present in the question, capped at 1.0.
     *
     * A multi-word keyword ("bank account") is matched as a phrase against the
     * raw question, so word order still counts where it matters.
     *
     * @param  string[]  $keywords
     */
    public function score(string $question, array $keywords): float
    {
        if ($keywords === []) {
            return 0.0;
        }

        $tokens = $this->tokenise($question);
        $lower = mb_strtolower($question);
        $hits = 0;

        foreach ($keywords as $keyword) {
            $keyword = mb_strtolower($keyword);

            $matched = str_contains($keyword, ' ')
                ? str_contains($lower, $keyword)
                : in_array($keyword, $tokens, true);

            if ($matched) {
                $hits++;
            }
        }

        if ($hits === 0) {
            return 0.0;
        }

        // Two hits out of a long keyword list still signals the topic strongly,
        // so confidence grows with hit count rather than with coverage alone.
        return min(1.0, $hits / min(count($keywords), 3));
    }
}
