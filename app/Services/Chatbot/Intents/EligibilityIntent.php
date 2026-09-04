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

/**
 * "Am I eligible?" and "Why was I rejected?"
 *
 * When an assessment exists, the reasons are read back from the stored breakdown
 * the Strategy chain produced, so the applicant sees the actual basis of the
 * decision rather than a generic explanation.
 */
class EligibilityIntent implements ChatIntentInterface
{
    public function __construct(private readonly KeywordMatcher $matcher) {}

    public function name(): string
    {
        return 'eligibility';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'eligible', 'eligibility', 'qualify', 'qualified', 'rejected',
            'refused', 'declined', 'score', 'threshold', 'income limit', 'b40',
            'criteria', 'requirements',

            // "Was I rejected?" is a status question; "why was I rejected?" is
            // this one. The distinguishing word is a stop word, so it is matched
            // as a phrase against the raw question instead of as a token.
            'why was', 'why did', 'why is', 'why am', 'not approved',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        $application = $user->applications()->with('aidProgram')->latest()->first();
        $breakdown = $application?->eligibility_breakdown;

        if (! $breakdown) {
            return new ChatReply(
                message: 'Eligibility is based on your gross monthly household income against the programme threshold, adjusted upward for each dependent you support. Households affected by a declared disaster, and those including a registered person with disability, receive additional priority.',
                suggestions: ['What programmes are available?', 'How do I apply?'],
                intent: $this->name(),
            );
        }

        // Every reason recorded by every strategy that applied.
        $reasons = collect($breakdown['strategies'] ?? [])
            ->flatMap(fn (array $strategy) => $strategy['reasons'] ?? [])
            ->take(4);

        $headline = $breakdown['eligible']
            ? "You met the criteria, with a priority score of {$breakdown['blended_score']} out of 100."
            : 'You did not meet the criteria for this programme.';

        $detail = $reasons->isNotEmpty()
            ? ' The assessment recorded: '.$reasons->implode(' ')
            : '';

        return new ChatReply(
            message: $headline.$detail,
            suggestions: ['What is my application status?', 'What programmes are available?'],
            linkUrl: route('applications.show', $application),
            linkLabel: 'See full assessment',
            intent: $this->name(),
        );
    }
}
