<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\AidProgram\AidProgramService;
use App\Services\Chatbot\ChatIntentInterface;
use App\Services\Chatbot\ChatReply;
use App\Services\Chatbot\KeywordMatcher;

/**
 * "What documents do I need?"
 *
 * The list is not hard-coded here — it is asked of the Factory-backed programme
 * service, so the assistant can never drift out of step with the real rule.
 */
class RequiredDocumentsIntent implements ChatIntentInterface
{
    public function __construct(
        private readonly KeywordMatcher $matcher,
        private readonly AidProgramService $programService,
    ) {}

    public function name(): string
    {
        return 'required_documents';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'document', 'documents', 'upload', 'attach', 'file', 'files',
            'proof', 'payslip', 'nric', 'ic', 'certificate', 'evidence', 'need',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        $application = $user->applications()->with(['aidProgram', 'documents'])->latest()->first();

        if (! $application) {
            return new ChatReply(
                message: 'The documents you need depend on the programme you apply for. Most ask for a copy of your NRIC and proof of income, such as a recent payslip. Open a programme to see its exact list.',
                suggestions: ['What programmes are available?', 'How do I apply?'],
                linkUrl: route('aid-programs.index'),
                linkLabel: 'Browse programmes',
                intent: $this->name(),
            );
        }

        $required = $this->programService->requiredDocumentsFor($application->aidProgram);
        $uploaded = $application->documents->pluck('document_type')->all();
        $missing = array_diff($required, $uploaded);

        $readable = fn (array $types) => implode(', ', array_map(
            fn ($type) => str_replace('_', ' ', $type),
            $types
        ));

        $message = $missing === []
            ? "You have uploaded everything \"{$application->aidProgram->title}\" requires (".$readable($required).'). Nothing further is needed.'
            : "\"{$application->aidProgram->title}\" requires: ".$readable($required).'. You still need to upload: '.$readable($missing).'.';

        return new ChatReply(
            message: $message.' Files may be PNG, JPG or PDF, up to 4 MB.',
            suggestions: ['What is my application status?'],
            linkUrl: route('applications.show', $application),
            linkLabel: 'Upload documents',
            intent: $this->name(),
        );
    }
}
