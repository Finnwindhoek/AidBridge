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

/** "How do I apply?" — the end-to-end process, in order. */
class HowToApplyIntent implements ChatIntentInterface
{
    public function __construct(private readonly KeywordMatcher $matcher) {}

    public function name(): string
    {
        return 'how_to_apply';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'apply', 'application form', 'register', 'start', 'submit',
            'how do', 'process', 'steps', 'new application',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        return new ChatReply(
            message: 'Applying takes four steps. First, choose an open programme and start an application — this saves as a draft. '
                .'Second, fill in your household income and number of dependents. Third, upload the documents that programme requires. '
                .'Fourth, submit it. You can edit a draft freely, but once submitted it is locked while an officer reviews it. '
                .'You may only hold one application per programme.',
            suggestions: ['What documents do I need?', 'How long does it take?'],
            linkUrl: route('applications.create'),
            linkLabel: 'Start an application',
            intent: $this->name(),
        );
    }
}
