<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Http\Controllers\Api;

use App\Enums\DisbursementStatus;
use App\Http\Controllers\Controller;
use App\Repositories\DisbursementRepositoryInterface;

/**
 * MODULE 4 — Fund Allocation & Disbursement Tracking: service exposure.
 *
 * Publishes ledger totals so Module 5 can report on money movement without
 * querying the disbursements table directly. Every figure comes from the
 * repository, so the web service and the internal ledger cannot disagree.
 */
class DisbursementApiController extends Controller
{
    public function __construct(private readonly DisbursementRepositoryInterface $repository) {}

    public function summary(): array
    {
        $totals = $this->repository->totalsByStatus();

        $byStatus = [];
        foreach (DisbursementStatus::cases() as $case) {
            $row = $totals[$case->value] ?? null;
            $byStatus[] = [
                'status' => $case->value,
                'label' => $case->label(),
                'count' => (int) ($row->count ?? 0),
                'total' => round((float) ($row->total ?? 0), 2),
            ];
        }

        return [
            'currency' => 'MYR',
            'settled_total' => $this->repository->totalDisbursed(),
            'by_status' => $byStatus,
        ];
    }
}
