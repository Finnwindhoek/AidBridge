<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Integration;

use App\Models\User;

/**
 * MODULE 2 (Applications & Documents) consumes MODULE 1 (Aid Programme
 * Management).
 *
 * Reads the catalogue of programmes currently open for application over the
 * REST API, so Module 2 never queries Module 1's tables directly.
 */
class ProgrammeCatalogueClient extends InternalServiceClient
{
    protected function sourceModule(): string
    {
        return 'Module 2 — Applications & Documents';
    }

    protected function targetModule(): string
    {
        return 'Module 1 — Aid Programme Management';
    }

    protected function functionName(): string
    {
        return 'getOpenProgrammes';
    }

    public function openProgrammes(User $actor): IntegrationResult
    {
        return $this->call($actor, '/api/programmes');
    }
}
