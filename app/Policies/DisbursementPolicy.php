<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Policies;

use App\Models\Disbursement;
use App\Models\User;

class DisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Disbursement $disbursement): bool
    {
        return $user->isAdmin() || $user->id === $disbursement->application->user_id;
    }

    /** Every mutation of the financial ledger is administrator-only. */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
