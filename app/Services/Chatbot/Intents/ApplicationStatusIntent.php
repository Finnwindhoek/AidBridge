<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Chatbot\Intents;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Services\Chatbot\ChatIntentInterface;
use App\Services\Chatbot\ChatReply;
use App\Services\Chatbot\KeywordMatcher;

/** "Where is my application?" — answered from the asker's own records. */
class ApplicationStatusIntent implements ChatIntentInterface
{
    public function __construct(private readonly KeywordMatcher $matcher) {}

    public function name(): string
    {
        return 'application_status';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'status', 'application', 'applications', 'progress', 'approved',
            'rejected', 'decision', 'reviewed', 'review', 'pending', 'happening',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        // Scoped to the asker's own relation, so one beneficiary can never be
        // shown another's case however the question is phrased.
        $application = $user->applications()
            ->with(['aidProgram', 'disbursement'])
            ->latest()
            ->first();

        if (! $application) {
            return new ChatReply(
                message: 'You have not applied for anything yet. Browse the open programmes to make your first application.',
                suggestions: ['What programmes are available?', 'How do I apply?'],
                linkUrl: route('aid-programs.index'),
                linkLabel: 'Browse programmes',
                intent: $this->name(),
            );
        }

        $message = match ($application->status) {
            ApplicationStatus::Draft => "Your application for \"{$application->aidProgram->title}\" is still a draft. It has not been submitted yet, so no one is reviewing it. Open it to finish and submit.",
            ApplicationStatus::Submitted => "Your application for \"{$application->aidProgram->title}\" was submitted on {$application->submitted_at?->format('d M Y')} and is waiting to be picked up by a reviewing officer.",
            ApplicationStatus::UnderReview => "Your application for \"{$application->aidProgram->title}\" is being assessed right now. You will be notified by email as soon as a decision is recorded.",
            ApplicationStatus::Approved => "Good news — your application for \"{$application->aidProgram->title}\" was approved on {$application->decided_at?->format('d M Y')}.",
            ApplicationStatus::Rejected => "Your application for \"{$application->aidProgram->title}\" was not approved on {$application->decided_at?->format('d M Y')}. Ask me why it was rejected to see the assessment detail.",
            ApplicationStatus::Withdrawn => "Your application for \"{$application->aidProgram->title}\" was withdrawn, so it is no longer being processed.",
        };

        $suggestions = match ($application->status) {
            ApplicationStatus::Approved => ['Where is my payment?'],
            ApplicationStatus::Rejected => ['Why was I rejected?'],
            ApplicationStatus::Draft => ['What documents do I need?'],
            default => ['What documents do I need?', 'How long does it take?'],
        };

        return new ChatReply(
            message: $message,
            suggestions: $suggestions,
            linkUrl: route('applications.show', $application),
            linkLabel: 'View application',
            intent: $this->name(),
        );
    }
}
