<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 5 — Reporting & Monitoring
 * Author: Ng Yu Xun
 */

namespace App\Services\Integration;

use App\Models\User;

/**
 * MODULE 5 (Reporting & Monitoring) consumes MODULE 4 (Fund Allocation &
 * Disbursement).
 *
 * Reads ledger totals over the REST API instead of querying the disbursements
 * table, so the reporting module cannot drift from the ledger's own view of
 * what has been paid.
 */
class DisbursementSummaryClient extends InternalServiceClient
{
    protected function sourceModule(): string
    {
        return 'Module 5 — Reporting & Monitoring';
    }

    protected function targetModule(): string
    {
        return 'Module 4 — Fund Allocation & Disbursement';
    }

    protected function functionName(): string
    {
        return 'getDisbursementSummary';
    }

    public function summary(User $actor): IntegrationResult
    {
        return $this->call($actor, '/api/disbursements/summary');
    }
}
