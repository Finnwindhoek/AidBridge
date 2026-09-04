<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Chatbot\Intents;

use App\Models\AidProgram;
use App\Models\User;
use App\Services\Chatbot\ChatIntentInterface;
use App\Services\Chatbot\ChatReply;
use App\Services\Chatbot\KeywordMatcher;

/** "What aid is available?" — reads the live list of open programmes. */
class ProgrammeListIntent implements ChatIntentInterface
{
    public function __construct(private readonly KeywordMatcher $matcher) {}

    public function name(): string
    {
        return 'programme_list';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'programme', 'programmes', 'program', 'programs', 'available',
            'aid', 'scheme', 'schemes', 'help', 'offer', 'grant', 'voucher',
            'subsidy', 'assistance',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        $open = AidProgram::acceptingApplications()->orderBy('title')->take(5)->get();

        if ($open->isEmpty()) {
            return new ChatReply(
                message: 'No programmes are open for application at the moment. New rounds are announced periodically, so it is worth checking back.',
                suggestions: ['How do I apply?'],
                intent: $this->name(),
            );
        }

        $lines = $open->map(fn (AidProgram $p) => sprintf(
            '%s (%s, up to RM %s)',
            $p->title,
            $p->type->label(),
            number_format((float) $p->payout_amount, 0),
        ))->implode('; ');

        return new ChatReply(
            message: "There are {$open->count()} programmes open right now: {$lines}. Each has its own income threshold and document requirements.",
            suggestions: ['How do I apply?', 'Am I eligible?'],
            linkUrl: route('aid-programs.index'),
            linkLabel: 'Browse programmes',
            intent: $this->name(),
        );
    }
}
