<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Services\Disbursement\States;

use App\Enums\DisbursementStatus;

/** Created against an approved application, awaiting an administrator's sign-off. */
class PendingState extends DisbursementState
{
    public function status(): DisbursementStatus
    {
        return DisbursementStatus::Pending;
    }

    public function allowedTransitions(): array
    {
        // A pending payout can only be approved or abandoned; it can never jump
        // straight to Disbursed, which is what stops an unauthorised payout.
        return [DisbursementStatus::Approved, DisbursementStatus::Failed];
    }
}
