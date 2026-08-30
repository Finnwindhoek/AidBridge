<?php

namespace App\Services\Chatbot;

use App\Models\User;
use App\Services\Chatbot\Intents\FallbackIntent;

/**
 * Context class for the assistant's Strategy pattern.
 *
 * Scores the question against every registered intent and lets the most
 * confident one answer. The intent list is injected by the container (see
 * AppServiceProvider), so the assistant's coverage is configuration rather than
 * a match statement that has to be edited for every new topic.
 */
class ChatbotService
{
    /**
     * Minimum confidence before an intent may answer.
     *
     * Set deliberately low-but-nonzero: a single strong keyword should be enough
     * to answer, but a question with no recognisable keyword at all must fall
     * through to the honest "I did not understand" rather than be answered by
     * whichever intent happened to score fractionally above zero.
     */
    public const CONFIDENCE_THRESHOLD = 0.3;

    /** Longest question accepted; anything beyond this is a paste, not a question. */
    public const MAX_QUESTION_LENGTH = 300;

    /**
     * @param  iterable<ChatIntentInterface>  $intents
     */
    public function __construct(
        private readonly iterable $intents,
        private readonly FallbackIntent $fallback,
    ) {}

    public function ask(string $question, User $user): ChatReply
    {
        $question = trim(mb_substr($question, 0, self::MAX_QUESTION_LENGTH));

        if ($question === '') {
            return $this->fallback->respond($question, $user);
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($this->intents as $intent) {
            $score = $intent->scoreFor($question);

            // Strictly greater, so the first intent registered wins a tie. That
            // makes the ordering in AppServiceProvider a deliberate priority.
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $intent;
            }
        }

        return $best !== null && $bestScore >= self::CONFIDENCE_THRESHOLD
            ? $best->respond($question, $user)
            : $this->fallback->respond($question, $user);
    }

    /** Opening prompts offered before the user has typed anything. */
    public function starterQuestions(): array
    {
        return [
            'What is my application status?',
            'Where is my payment?',
            'What documents do I need?',
            'How do I apply?',
        ];
    }
}
