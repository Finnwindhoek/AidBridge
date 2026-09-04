<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Chatbot\Intents;

use App\Enums\ApplicationStatus;
use App\Enums\DisbursementStatus;
use App\Models\User;
use App\Services\Chatbot\ChatIntentInterface;
use App\Services\Chatbot\ChatReply;
use App\Services\Chatbot\KeywordMatcher;

/** "Where is my money?" — reads the ledger record for the asker's application. */
class PaymentStatusIntent implements ChatIntentInterface
{
    public function __construct(private readonly KeywordMatcher $matcher) {}

    public function name(): string
    {
        return 'payment_status';
    }

    public function scoreFor(string $question): float
    {
        return $this->matcher->score($question, [
            'payment', 'paid', 'money', 'cash', 'disbursement', 'disbursed',
            'transfer', 'bank', 'funds', 'ringgit', 'rm', 'receive', 'received',
        ]);
    }

    public function respond(string $question, User $user): ChatReply
    {
        $application = $user->applications()
            ->with(['disbursement', 'aidProgram'])
            ->latest()
            ->first();

        $disbursement = $application?->disbursement;

        if (! $disbursement) {
            $message = match (true) {
                $application === null => 'You do not have a payment yet because you have not applied for anything.',
                $application->status === ApplicationStatus::Approved => 'Your application was approved and a payment is being prepared. It will appear here once it has been scheduled.',
                $application->status->isFinal() => 'There is no payment for your latest application, because it was not approved.',
                default => 'No payment has been scheduled yet. A payment is only created once your application has been approved.',
            };

            return new ChatReply(
                message: $message,
                suggestions: ['What is my application status?'],
                intent: $this->name(),
            );
        }

        $amount = 'RM '.number_format((float) $disbursement->amount, 2);

        $message = match ($disbursement->status) {
            DisbursementStatus::Pending => "Your payment of {$amount} has been raised and is waiting for final approval by the aid office.",
            DisbursementStatus::Approved => "Your payment of {$amount} has been approved and is queued with the bank.",
            DisbursementStatus::Disbursed => "Your payment of {$amount} has been sent to your bank. Please allow one to three working days for it to clear.",
            DisbursementStatus::Reconciled => "Your payment of {$amount} is complete and has been confirmed against the bank statement.",
            DisbursementStatus::Failed => "Your payment of {$amount} did not go through. Reason given: {$disbursement->failure_reason}. Please contact the aid office to correct your details.",
        };

        return new ChatReply(
            message: $message." Your payment reference is {$disbursement->reference_code}.",
            suggestions: ['What is my application status?'],
            linkUrl: route('disbursements.show', $disbursement),
            linkLabel: 'View payment record',
            intent: $this->name(),
        );
    }
}
