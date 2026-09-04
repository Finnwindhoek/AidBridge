<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Repositories;

use App\Enums\DisbursementStatus;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\Disbursement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * REPOSITORY PATTERN — Module 4.
 *
 * Every read and write against the financial ledger goes through this contract.
 * Isolating the queries means the locking and atomicity rules that protect payouts
 * live in exactly one implementation, and the service layer can be tested against
 * a fake without touching MySQL.
 */
interface DisbursementRepositoryInterface
{
    public function findByReference(string $referenceCode): ?Disbursement;

    /** Fetches a row with a pessimistic write lock. Must run inside a transaction. */
    public function lockForUpdate(int $id): ?Disbursement;

    public function existsForApplication(Application $application): bool;

    public function create(array $attributes): Disbursement;

    public function updateStatus(Disbursement $disbursement, DisbursementStatus $status, array $extra = []): Disbursement;

    /** Atomically commits budget, failing rather than overdrawing the programme. */
    public function commitBudget(AidProgram $program, float $amount): bool;

    /** Returns committed budget to the programme when a payout fails. */
    public function releaseBudget(AidProgram $program, float $amount): void;

    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function totalsByStatus(): Collection;

    public function totalDisbursed(?int $programId = null): float;
}
