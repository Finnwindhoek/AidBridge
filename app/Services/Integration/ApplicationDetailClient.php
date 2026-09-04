<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 3 — Verification & Eligibility Assessment
 * Author: Chia Yi Kuang
 */

namespace App\Services\Integration;

use App\Models\Application;
use App\Models\User;

/**
 * MODULE 3 (Verification & Eligibility) consumes MODULE 2 (Applications &
 * Documents).
 *
 * Reads an applicant's declared household data and document status over the
 * REST API before assessing it, so the assessment module depends on Module 2's
 * published contract rather than on its schema.
 */
class ApplicationDetailClient extends InternalServiceClient
{
    protected function sourceModule(): string
    {
        return 'Module 3 — Verification & Eligibility';
    }

    protected function targetModule(): string
    {
        return 'Module 2 — Applications & Documents';
    }

    protected function functionName(): string
    {
        return 'getApplicationDetail';
    }

    public function detail(User $actor, Application $application): IntegrationResult
    {
        return $this->call($actor, '/api/applications/'.$application->reference);
    }
}
