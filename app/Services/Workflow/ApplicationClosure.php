<?php

namespace App\Services\Workflow;

use App\Models\Application;
use App\Models\Disbursement;

/**
 * Immutable outcome of ApplicationWorkflowFacade::close().
 *
 * The facade hides the sequence, but the caller still needs to know what actually
 * happened — whether a payout was raised, or why it was not — so it gets one value
 * object back rather than having to interrogate three services itself.
 */
final readonly class ApplicationClosure
{
    public function __construct(
        public Application $application,
        public bool $approved,
        public ?Disbursement $disbursement = null,
        public ?string $disbursementError = null,
    ) {}

    /** True when an approval completed without its payout being raised. */
    public function needsManualDisbursement(): bool
    {
        return $this->approved && $this->disbursement === null;
    }

    /** Ready-to-flash description of the whole closing sequence. */
    public function summary(): string
    {
        if (! $this->approved) {
            return 'Application rejected and the applicant has been notified.';
        }

        if ($this->disbursement) {
            return sprintf(
                'Application approved. Disbursement %s raised for RM %s and is awaiting approval.',
                $this->disbursement->reference_code,
                number_format((float) $this->disbursement->amount, 2),
            );
        }

        return 'Application approved, but the disbursement could not be raised automatically: '
            .$this->disbursementError.' You can schedule it manually.';
    }
}
