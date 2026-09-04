<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Application;

use App\Enums\ApplicationStatus;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\User;
use App\Services\AidProgram\AidProgramService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Business rules for Module 2. Audit logging and notifications are NOT called
 * from here — ApplicationObserver reacts to the model events instead.
 */
class ApplicationService
{
    public function __construct(
        private readonly AidProgramService $programService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(User $user, AidProgram $program, array $data): Application
    {
        if (! $program->isAcceptingApplications()) {
            throw new RuntimeException('This programme is not currently accepting applications.');
        }

        // Anti double-dipping: one application per beneficiary per programme. The
        // unique index on (user_id, aid_program_id) is the hard guarantee; this is
        // the friendly error.
        if ($user->applications()->where('aid_program_id', $program->id)->exists()) {
            throw new RuntimeException('You have already applied to this programme.');
        }

        return Application::create([
            'user_id' => $user->id,
            'aid_program_id' => $program->id,
            'status' => ApplicationStatus::Draft,
            'household_income' => $data['household_income'],
            'dependents_count' => $data['dependents_count'],
            'state' => $data['state'] ?? $user->state,
            'is_disaster_victim' => (bool) ($data['is_disaster_victim'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(Application $application, array $data): Application
    {
        if (! $application->isEditable()) {
            throw new RuntimeException('A submitted application can no longer be edited.');
        }

        $application->update($data);

        return $application->refresh();
    }

    /**
     * Moves a draft to Submitted once its evidence is complete. The Observer picks
     * up the status change and notifies the applicant.
     */
    public function submit(Application $application): Application
    {
        if (! $application->isEditable()) {
            throw new RuntimeException('This application has already been submitted.');
        }

        if (! $application->aidProgram->isAcceptingApplications()) {
            throw new RuntimeException('This programme has closed and can no longer accept submissions.');
        }

        $application->load('documents');

        $required = $this->programService->requiredDocumentsFor($application->aidProgram);
        $present = $application->documents->pluck('document_type')->all();
        $missing = array_diff($required, $present);

        if ($missing !== []) {
            throw new RuntimeException(
                'Please upload these documents first: '.implode(', ', array_map(
                    fn ($type) => str_replace('_', ' ', $type),
                    $missing
                ))
            );
        }

        // forceFill: submitted_at is deliberately not fillable, so a request can
        // never backdate a submission. The value is set here from the server clock.
        $application->forceFill([
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        return $application->refresh();
    }

    public function withdraw(Application $application): Application
    {
        if ($application->status->isFinal()) {
            throw new RuntimeException('This application has already been finalised.');
        }

        $application->update(['status' => ApplicationStatus::Withdrawn]);

        return $application->refresh();
    }

    /** Admin action: takes a submitted application off the queue for assessment. */
    public function markUnderReview(Application $application, User $admin): Application
    {
        if ($application->status !== ApplicationStatus::Submitted) {
            throw new RuntimeException('Only submitted applications can be moved under review.');
        }

        $application->update(['status' => ApplicationStatus::UnderReview]);

        $this->auditLogger->log('application.review_started', $application, [], $admin->id);

        return $application->refresh();
    }

    /**
     * Records an admin decision. Wrapped in a transaction because approving also
     * commits programme budget in the disbursement step that follows.
     */
    public function decide(Application $application, User $admin, bool $approved, ?string $note = null): Application
    {
        if ($application->status->isFinal()) {
            throw new RuntimeException('A decision has already been recorded for this application.');
        }

        return DB::transaction(function () use ($application, $admin, $approved, $note) {
            // forceFill: decided_at and decided_by record who made the decision and
            // when. Both are excluded from $fillable so they cannot be spoofed by a
            // crafted request; they are written here from the authenticated actor.
            $application->forceFill([
                'status' => $approved ? ApplicationStatus::Approved : ApplicationStatus::Rejected,
                'decided_at' => now(),
                'decided_by' => $admin->id,
                'notes' => $note ?? $application->notes,
            ])->save();

            return $application->refresh();
        });
    }
}
