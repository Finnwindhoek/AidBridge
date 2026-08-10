<?php

namespace App\Services\Disbursement\States;

use App\Enums\DisbursementStatus;

/** Signed off and budget-committed; queued for the payment gateway. */
class ApprovedState extends DisbursementState
{
    public function status(): DisbursementStatus
    {
        return DisbursementStatus::Approved;
    }

    public function allowedTransitions(): array
    {
        return [DisbursementStatus::Disbursed, DisbursementStatus::Failed];
    }
}
