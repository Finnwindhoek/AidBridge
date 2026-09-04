<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Observers;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Notifications\ApplicationStatusChanged;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * OBSERVER PATTERN — Module 2.
 *
 * Listens to Application model events and reacts to them: writing the audit trail
 * and dispatching notifications. Because this is wired to model events, any code
 * path that changes an application — web controller, API, seeder, console command
 * — produces the same audit record without the caller having to remember.
 */
class ApplicationObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Application $application): void
    {
        $this->auditLogger->log('application.created', $application, [
            'aid_program_id' => $application->aid_program_id,
            'status' => $application->status->value,
        ]);
    }

    public function updated(Application $application): void
    {
        // Only the fields that actually changed are recorded, and PII is redacted
        // by the AuditLogger before it is written.
        $changes = $application->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $original = array_intersect_key($application->getOriginal(), $changes);

        $this->auditLogger->log('application.updated', $application, [
            'from' => $this->stringifyEnums($original),
            'to' => $this->stringifyEnums($changes),
        ]);

        // A status transition is the event beneficiaries care about.
        if (array_key_exists('status', $changes)) {
            $this->handleStatusChange($application, $original['status'] ?? null);
        }
    }

    public function deleted(Application $application): void
    {
        $this->auditLogger->log('application.deleted', $application, [
            'reference' => $application->reference,
        ]);
    }

    private function handleStatusChange(Application $application, mixed $previous): void
    {
        $previousStatus = $previous instanceof ApplicationStatus
            ? $previous
            : ApplicationStatus::tryFrom((string) $previous);

        $this->auditLogger->log('application.status_changed', $application, [
            'from' => $previousStatus?->value,
            'to' => $application->status->value,
        ]);

        // Drafts are private working copies; the applicant does not need telling
        // that their own draft exists.
        if ($application->status === ApplicationStatus::Draft) {
            return;
        }

        try {
            $application->user->notify(
                new ApplicationStatusChanged($application, $previousStatus)
            );
        } catch (\Throwable $e) {
            // A notification channel being down must never roll back the decision
            // that was just recorded, so this is logged rather than rethrown.
            Log::warning('AidBridge: application status notification failed.', [
                'application' => $application->reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Enum-cast attributes must be flattened before they reach a JSON column. */
    private function stringifyEnums(array $attributes): array
    {
        return array_map(
            fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
            $attributes
        );
    }
}
