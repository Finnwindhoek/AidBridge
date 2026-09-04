<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

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
