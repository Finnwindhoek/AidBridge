<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 1 — Aid Programme Management
 * Author: Liong Ka Kien
 */

namespace App\Services\Integration;

use App\Models\User;

/**
 * MODULE 1 (Aid Programme Management) consumes MODULE 5 (Reporting &
 * Monitoring).
 *
 * Reads budget-utilisation figures over the REST API so a programme
 * administrator can see how each programme's funding is performing without
 * Module 1 recomputing aggregates that Module 5 already owns.
 *
 * This call closes the loop: every module both exposes a service and consumes
 * one, which is what makes the integration bidirectional rather than a chain.
 */
class DashboardMetricsClient extends InternalServiceClient
{
    protected function sourceModule(): string
    {
        return 'Module 1 — Aid Programme Management';
    }

    protected function targetModule(): string
    {
        return 'Module 5 — Reporting & Monitoring';
    }

    protected function functionName(): string
    {
        return 'getDashboardMetrics';
    }

    public function budgetUtilisation(User $actor): IntegrationResult
    {
        return $this->call($actor, '/api/reports/metrics');
    }
}
