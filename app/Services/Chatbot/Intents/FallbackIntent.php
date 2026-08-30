<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Chatbot\ChatIntentInterface;
use App\Services\Chatbot\ChatReply;

/**
 * The answer of last resort.
 *
 * Deliberately scores 0.0 so it never competes: ChatbotService falls back to it
 * only when no other intent clears the confidence threshold. Saying plainly that
 * it did not understand, and offering what it *can* answer, beats guessing at a
 * question about someone's aid money.
 */
class FallbackIntent implements ChatIntentInterface
{
    public function name(): string
    {
        return 'fallback';
    }

    public function scoreFor(string $question): float
    {
        return 0.0;
    }

    public function respond(string $question, User $user): ChatReply
    {
        return new ChatReply(
            message: 'Sorry, I did not understand that. I can help with your application status, payments, required documents, eligibility, and how to apply. '
                .'For anything else, please contact the aid office directly.',
            suggestions: [
                'What is my application status?',
                'Where is my payment?',
                'What documents do I need?',
                'How do I apply?',
            ],
            intent: $this->name(),
        );
    }
}
