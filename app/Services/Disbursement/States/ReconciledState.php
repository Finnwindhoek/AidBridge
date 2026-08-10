<?php

namespace App\Services\Disbursement\States;

use App\Enums\DisbursementStatus;

/** Matched against the bank statement. Terminal: the ledger entry is now closed. */
class ReconciledState extends DisbursementState
{
    public function status(): DisbursementStatus
    {
        return DisbursementStatus::Reconciled;
    }

    public function allowedTransitions(): array
    {
        return [];
    }
}
