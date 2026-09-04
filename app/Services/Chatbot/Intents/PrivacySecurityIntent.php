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

/** "Is my NRIC safe?" — the data-handling questions applicants actually ask. */
class PrivacySecurityIntent implements ChatIntentInterface
{
    public function __construct(private readonly KeywordMatcher $matcher) {}

    public function name(): string
    {
        return 'privacy_security';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'privacy', 'private', 'secure', 'security', 'safe', 'data',
            'encrypted', 'encryption', 'stored', 'share', 'shared', 'confidential',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        return new ChatReply(
            message: 'Your NRIC is encrypted before it is written to the database and is never displayed in full — only the last four digits are ever shown, and it is excluded from every export. '
                .'Uploaded documents are stored outside the public web folder and can only be opened through a time-limited link that still checks your permissions. '
                .'Every action taken on your record is written to an audit trail, with sensitive values stripped out before storage.',
            suggestions: ['What documents do I need?'],
            intent: $this->name(),
        );
    }
}
