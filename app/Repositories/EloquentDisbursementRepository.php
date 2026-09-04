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
use Illuminate\Support\Facades\DB;

class EloquentDisbursementRepository implements DisbursementRepositoryInterface
{
    public function findByReference(string $referenceCode): ?Disbursement
    {
        return Disbursement::where('reference_code', $referenceCode)->first();
    }

    public function lockForUpdate(int $id): ?Disbursement
    {
        return Disbursement::whereKey($id)->lockForUpdate()->first();
    }

    public function existsForApplication(Application $application): bool
    {
        return Disbursement::where('application_id', $application->id)
            // A failed payout does not count: it must be possible to raise a
            // replacement. Anything else means the application is already paid.
            ->where('status', '!=', DisbursementStatus::Failed->value)
            ->exists();
    }

    public function create(array $attributes): Disbursement
    {
        return Disbursement::create($attributes);
    }

    public function updateStatus(Disbursement $disbursement, DisbursementStatus $status, array $extra = []): Disbursement
    {
        // forceFill, not update(): the lifecycle columns (approved_at, approved_by,
        // disbursed_at, reconciled_at, bank_reference, failure_reason) are kept out
        // of $fillable on purpose so no request payload can ever set them. They are
        // written here, at the trusted repository boundary, from values the service
        // layer computed — never from user input. Using update() would silently
        // discard them and leave the ledger without its audit timestamps.
        $disbursement->forceFill(array_merge(['status' => $status], $extra))->save();

        return $disbursement->refresh();
    }

    /**
     * Conditional UPDATE: the `budget_remaining >= amount` predicate is evaluated
     * by MySQL as part of the write, so two concurrent approvals cannot both see
     * sufficient budget and overdraw the programme. Returns false if it did not
     * apply, which the caller treats as "insufficient funds".
     */
    public function commitBudget(AidProgram $program, float $amount): bool
    {
        // decrement() emits `budget_remaining = budget_remaining - ?` with the
        // amount bound, so the arithmetic happens in the database in one statement.
        $affected = AidProgram::whereKey($program->id)
            ->where('budget_remaining', '>=', $amount)
            ->decrement('budget_remaining', $amount);

        if ($affected > 0) {
            $program->refresh();
        }

        return $affected > 0;
    }

    public function releaseBudget(AidProgram $program, float $amount): void
    {
        AidProgram::whereKey($program->id)->increment('budget_remaining', $amount);

        // Clamp: never credit back more than was allocated. Callers run this
        // inside a transaction, so the credit and the clamp settle together.
        AidProgram::whereKey($program->id)
            ->whereColumn('budget_remaining', '>', 'budget_allocated')
            ->update(['budget_remaining' => DB::raw('budget_allocated')]);

        $program->refresh();
    }

    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Disbursement::query()
            ->with(['application.user', 'application.aidProgram'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['programme'] ?? null, fn ($q, $slug) => $q->whereHas(
                'application.aidProgram',
                fn ($p) => $p->where('slug', $slug)
            ))
            ->when($filters['reference'] ?? null, fn ($q, $ref) => $q->where('reference_code', 'like', '%'.$ref.'%'))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function totalsByStatus(): Collection
    {
        return Disbursement::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');
    }

    public function totalDisbursed(?int $programId = null): float
    {
        return (float) Disbursement::query()
            ->settled()
            ->when($programId, fn ($q, $id) => $q->whereHas(
                'application',
                fn ($a) => $a->where('aid_program_id', $id)
            ))
            ->sum('amount');
    }
}
