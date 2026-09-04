<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Services\Integration;

use App\Models\Application;
use App\Models\User;

/**
 * MODULE 4 (Fund Allocation & Disbursement) consumes MODULE 3 (Verification &
 * Eligibility).
 *
 * Reads the recorded eligibility outcome over the REST API. Module 4 needs the
 * score because the Emergency Grant payout formula scales with it, and it needs
 * the recommendation and reasons so a payout can be explained and audited.
 */
class EligibilityClient extends InternalServiceClient
{
    protected function sourceModule(): string
    {
        return 'Module 4 — Fund Allocation & Disbursement';
    }

    protected function targetModule(): string
    {
        return 'Module 3 — Verification & Eligibility';
    }

    protected function functionName(): string
    {
        return 'getApplicationEligibility';
    }

    public function forApplication(User $actor, Application $application): IntegrationResult
    {
        return $this->call($actor, '/api/applications/'.$application->reference.'/eligibility');
    }
}
