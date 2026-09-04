<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

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
