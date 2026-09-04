<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Enums;

use App\Services\Disbursement\States\ApprovedState;
use App\Services\Disbursement\States\DisbursedState;
use App\Services\Disbursement\States\DisbursementState;
use App\Services\Disbursement\States\FailedState;
use App\Services\Disbursement\States\PendingState;
use App\Services\Disbursement\States\ReconciledState;

enum DisbursementStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Disbursed = 'disbursed';
    case Reconciled = 'reconciled';
    case Failed = 'failed';

    /**
     * Maps the persisted status column onto its State pattern implementation.
     * This is the single place where a status string becomes behaviour.
     */
    public function stateClass(): string
    {
        return match ($this) {
            self::Pending => PendingState::class,
            self::Approved => ApprovedState::class,
            self::Disbursed => DisbursedState::class,
            self::Reconciled => ReconciledState::class,
            self::Failed => FailedState::class,
        };
    }

    public function makeState(): DisbursementState
    {
        $class = $this->stateClass();

        return new $class();
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Approved => 'Approved',
            self::Disbursed => 'Disbursed',
            self::Reconciled => 'Reconciled',
            self::Failed => 'Failed',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Approved => 'info',
            self::Disbursed => 'primary',
            self::Reconciled => 'success',
            self::Failed => 'danger',
        };
    }

    /** Bootstrap Icons name paired with the badge, so status is not colour-only. */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass-split',
            self::Approved => 'check2-circle',
            self::Disbursed => 'send-fill',
            self::Reconciled => 'shield-check',
            self::Failed => 'x-circle-fill',
        };
    }
}
