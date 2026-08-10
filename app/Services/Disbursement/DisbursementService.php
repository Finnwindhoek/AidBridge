<?php

namespace App\Services\Disbursement;

use App\Enums\ApplicationStatus;
use App\Enums\DisbursementStatus;
use App\Models\Application;
use App\Models\Disbursement;
use App\Models\User;
use App\Models\WebhookReceipt;
use App\Repositories\DisbursementRepositoryInterface;
use App\Services\AidProgram\AidProgramService;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates Module 4.
 *
 * Every method that touches money runs inside DB::transaction() and moves the
 * record through the State pattern, so a partial failure can never leave the
 * ledger and the programme budget disagreeing.
 */
class DisbursementService
{
    public function __construct(
        private readonly DisbursementRepositoryInterface $repository,
        private readonly AidProgramService $programService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Raises a pending disbursement for an approved application. The payout amount
     * comes from the programme type (Factory) and the eligibility score (Strategy).
     */
    public function createForApplication(Application $application, User $admin): Disbursement
    {
        if ($application->status !== ApplicationStatus::Approved) {
            throw new RuntimeException('Only approved applications can be scheduled for payment.');
        }

        // Double-dipping guard. The unique index is the real guarantee; this is the
        // readable error.
        if ($this->repository->existsForApplication($application)) {
            throw new RuntimeException('A disbursement already exists for this application.');
        }

        $amount = $this->programService->payoutFor(
            $application->aidProgram,
            (int) $application->dependents_count,
            (int) ($application->eligibility_score ?? 0),
        );

        if ($amount <= 0) {
            throw new RuntimeException('The calculated payout is zero; check the programme configuration.');
        }

        $disbursement = $this->repository->create([
            'application_id' => $application->id,
            'amount' => $amount,
            'status' => DisbursementStatus::Pending,
            'payment_channel' => 'bank_transfer',
        ]);

        $this->auditLogger->log('disbursement.created', $disbursement, [
            'application' => $application->reference,
            'amount' => $amount,
        ], $admin->id);

        return $disbursement;
    }

    /**
     * Approves a payout and commits the money against the programme budget in the
     * same transaction, so budget can never be committed without a corresponding
     * approved row (or the reverse).
     */
    public function approve(Disbursement $disbursement, User $admin): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $admin) {
            // Re-read under a write lock so two admins clicking Approve at the same
            // moment cannot both pass the state check.
            $locked = $this->repository->lockForUpdate($disbursement->id);

            if (! $locked) {
                throw new RuntimeException('This disbursement no longer exists.');
            }

            $this->assertCanTransition($locked, DisbursementStatus::Approved);

            $program = $locked->application->aidProgram;

            if (! $this->repository->commitBudget($program, (float) $locked->amount)) {
                throw new RuntimeException(sprintf(
                    'Insufficient remaining budget in "%s". Required RM %s, available RM %s.',
                    $program->title,
                    number_format((float) $locked->amount, 2),
                    number_format((float) $program->budget_remaining, 2),
                ));
            }

            $updated = $this->repository->updateStatus($locked, DisbursementStatus::Approved, [
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

            $this->auditLogger->log('disbursement.approved', $updated, [
                'amount' => $updated->amount,
                'programme_budget_remaining' => $program->fresh()->budget_remaining,
            ], $admin->id);

            return $updated;
        });
    }

    /** Marks the money as sent. In production this follows the gateway's ack. */
    public function markDisbursed(Disbursement $disbursement, User $admin, ?string $bankReference = null): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $admin, $bankReference) {
            $locked = $this->repository->lockForUpdate($disbursement->id);
            $this->assertCanTransition($locked, DisbursementStatus::Disbursed);

            $updated = $this->repository->updateStatus($locked, DisbursementStatus::Disbursed, [
                'disbursed_at' => now(),
                'bank_reference' => $bankReference,
            ]);

            $this->auditLogger->log('disbursement.disbursed', $updated, [
                'bank_reference' => $bankReference,
            ], $admin->id);

            return $updated;
        });
    }

    /** Final step: matched against the bank statement. */
    public function reconcile(Disbursement $disbursement, User $admin): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $admin) {
            $locked = $this->repository->lockForUpdate($disbursement->id);
            $this->assertCanTransition($locked, DisbursementStatus::Reconciled);

            $updated = $this->repository->updateStatus($locked, DisbursementStatus::Reconciled, [
                'reconciled_at' => now(),
            ]);

            $this->auditLogger->log('disbursement.reconciled', $updated, [], $admin->id);

            return $updated;
        });
    }

    /**
     * Records a failed payout and hands the committed budget back to the programme
     * so it can fund somebody else.
     */
    public function fail(Disbursement $disbursement, string $reason, ?User $admin = null): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $reason, $admin) {
            $locked = $this->repository->lockForUpdate($disbursement->id);
            $this->assertCanTransition($locked, DisbursementStatus::Failed);

            // Budget is only released if it was actually committed, i.e. the record
            // got past Pending. Releasing from Pending would credit money that was
            // never debited.
            $wasCommitted = $locked->status !== DisbursementStatus::Pending;

            if ($wasCommitted) {
                $this->repository->releaseBudget($locked->application->aidProgram, (float) $locked->amount);
            }

            $updated = $this->repository->updateStatus($locked, DisbursementStatus::Failed, [
                'failure_reason' => substr($reason, 0, 255),
            ]);

            $this->auditLogger->log('disbursement.failed', $updated, [
                'reason' => $reason,
                'budget_released' => $wasCommitted,
            ], $admin?->id);

            return $updated;
        });
    }

    /**
     * Applies an inbound payment-gateway callback.
     *
     * IDEMPOTENCY: the webhook_receipts table has a unique index on the key. The
     * insert is attempted first, so a retried delivery hits the constraint and
     * returns early instead of paying out twice.
     */
    public function handleWebhook(string $idempotencyKey, string $eventType, string $referenceCode, array $payload): array
    {
        $disbursement = $this->repository->findByReference($referenceCode);

        if (! $disbursement) {
            return ['status' => 'ignored', 'message' => 'Unknown disbursement reference.'];
        }

        try {
            $receipt = DB::transaction(fn () => WebhookReceipt::create([
                'idempotency_key' => $idempotencyKey,
                'source' => 'payment_gateway',
                'event_type' => $eventType,
                'disbursement_id' => $disbursement->id,
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            $this->auditLogger->log('webhook.duplicate_ignored', $disbursement, [
                'idempotency_key' => $idempotencyKey,
                'event_type' => $eventType,
            ]);

            return ['status' => 'duplicate', 'message' => 'This callback has already been processed.'];
        }

        try {
            $result = match ($eventType) {
                'payment.completed' => $this->applyWebhookTransition(
                    $disbursement,
                    DisbursementStatus::Disbursed,
                    ['disbursed_at' => now(), 'bank_reference' => $payload['bank_reference'] ?? null],
                ),
                'payment.settled' => $this->applyWebhookTransition(
                    $disbursement,
                    DisbursementStatus::Reconciled,
                    ['reconciled_at' => now()],
                ),
                'payment.failed' => $this->fail(
                    $disbursement,
                    $payload['reason'] ?? 'Reported as failed by the payment gateway.',
                ),
                default => null,
            };

            if ($result === null) {
                return ['status' => 'ignored', 'message' => "Unsupported event type [{$eventType}]."];
            }

            $receipt->update(['processed_at' => now()]);

            $this->auditLogger->log('webhook.processed', $disbursement, [
                'event_type' => $eventType,
                'new_status' => $result->status->value,
            ]);

            return ['status' => 'processed', 'disbursement_status' => $result->status->value];
        } catch (RuntimeException $e) {
            // The receipt stays without processed_at, which is the signal for an
            // operator to investigate a callback that arrived out of order.
            $this->auditLogger->log('webhook.rejected', $disbursement, [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'rejected', 'message' => $e->getMessage()];
        }
    }

    private function applyWebhookTransition(Disbursement $disbursement, DisbursementStatus $target, array $extra): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $target, $extra) {
            $locked = $this->repository->lockForUpdate($disbursement->id);
            $this->assertCanTransition($locked, $target);

            return $this->repository->updateStatus($locked, $target, $extra);
        });
    }

    /** The State pattern check that guards every lifecycle move. */
    private function assertCanTransition(?Disbursement $disbursement, DisbursementStatus $target): void
    {
        if (! $disbursement) {
            throw new RuntimeException('This disbursement no longer exists.');
        }

        $state = $disbursement->state();

        if (! $state->canTransitionTo($target)) {
            throw new RuntimeException($state->transitionError($target));
        }
    }
}
