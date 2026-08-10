<?php

namespace App\Services\Disbursement\States;

use App\Enums\DisbursementStatus;

/**
 * The payment did not complete. Committed budget is released back to the
 * programme when a disbursement enters this state.
 */
class FailedState extends DisbursementState
{
    public function status(): DisbursementStatus
    {
        return DisbursementStatus::Failed;
    }

    public function allowedTransitions(): array
    {
        // A failed payout is retried by raising a fresh disbursement, so the failed
        // ledger row stays immutable as evidence.
        return [];
    }
}
