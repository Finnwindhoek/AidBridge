<?php

namespace App\Services\Disbursement\States;

use App\Enums\DisbursementStatus;

/** Money has left the account; awaiting the bank statement match. */
class DisbursedState extends DisbursementState
{
    public function status(): DisbursementStatus
    {
        return DisbursementStatus::Disbursed;
    }

    public function allowedTransitions(): array
    {
        // Still reversible: a gateway can report a bounced transfer after the fact.
        return [DisbursementStatus::Reconciled, DisbursementStatus::Failed];
    }
}
