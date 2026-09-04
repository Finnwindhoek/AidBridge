<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Services\Disbursement\States;

use App\Enums\DisbursementStatus;

/**
 * STATE PATTERN — Module 4.
 *
 * Each disbursement status is a class that answers "what may happen next?".
 * Putting the lifecycle rules here means an illegal transition — paying out a
 * disbursement that was never approved, reconciling one that never left the
 * queue — is rejected by the domain model rather than by a scattered `if`.
 *
 * Lifecycle: Pending -> Approved -> Disbursed -> Reconciled
 *            Approved/Disbursed -> Failed
 */
abstract class DisbursementState
{
    abstract public function status(): DisbursementStatus;

    /** Statuses this state is allowed to move to. */
    abstract public function allowedTransitions(): array;

    public function canTransitionTo(DisbursementStatus $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return $this->status()->label();
    }

    /** Whether an administrator may still act on the record in this state. */
    public function isActionable(): bool
    {
        return $this->allowedTransitions() !== [];
    }

    /** Explains a refusal, for the error the admin actually sees. */
    public function transitionError(DisbursementStatus $target): string
    {
        $allowed = array_map(fn (DisbursementStatus $s) => $s->label(), $this->allowedTransitions());

        $article = $this->article($this->label());

        return $allowed === []
            ? "{$article} {$this->label()} disbursement is final and cannot be changed."
            : sprintf(
                '%s %s disbursement cannot move to %s. Allowed next steps: %s.',
                $article,
                $this->label(),
                $target->label(),
                implode(', ', $allowed)
            );
    }

    private function article(string $word): string
    {
        return in_array(strtolower($word[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'An' : 'A';
    }
}
