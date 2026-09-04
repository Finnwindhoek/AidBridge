<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

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
