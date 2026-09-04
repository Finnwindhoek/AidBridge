<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Chatbot\ChatIntentInterface;
use App\Services\Chatbot\ChatReply;
use App\Services\Chatbot\KeywordMatcher;

/** "How long does it take?" */
class ProcessingTimeIntent implements ChatIntentInterface
{
    public function __construct(private readonly KeywordMatcher $matcher) {}

    public function name(): string
    {
        return 'processing_time';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'long', 'time', 'wait', 'waiting', 'take', 'takes', 'soon',
            'duration', 'delay', 'days', 'week', 'weeks',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        return new ChatReply(
            message: 'There is no fixed service standard, and times vary with how many applications are in the queue. '
                .'Applications are reviewed in priority order — the highest eligibility scores are assessed first, not the earliest submissions. '
                .'Once approved, a payment is raised immediately, but it still needs approval by the aid office before the bank transfer is sent. '
                .'You will be emailed at every status change, so you do not need to keep checking.',
            suggestions: ['What is my application status?', 'Where is my payment?'],
            intent: $this->name(),
        );
    }
}
