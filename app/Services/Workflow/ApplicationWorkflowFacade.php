<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 3 — Verification & Eligibility Assessment
 * Author: Chia Yi Kuang
 */

namespace App\Services\Workflow;

use App\Models\Application;
use App\Models\User;
use App\Services\Application\ApplicationService;
use App\Services\Disbursement\DisbursementService;
use App\Services\Eligibility\EligibilityService;
use App\Support\RequestContext;
use RuntimeException;

/**
 * FACADE PATTERN — the admin case-handling workflow.
 *
 * Reviewing and closing an aid application is not one operation but a sequence
 * spread across four subsystems: the Strategy chain scores the applicant, the
 * external registry is consulted, the application's status is moved, the Observer
 * fires the audit trail and the applicant's notification, and — on approval — the
 * ledger raises a payout sized by the programme's Factory type.
 *
 * Before this class, controllers had to know that ordering and drive it by hand.
 * The facade gives them two calls — review() and close() — and owns the
 * sequencing, the partial-failure rules and the cross-service error handling
 * behind them. The subsystems are untouched and still usable directly; a facade
 * simplifies access to a subsystem, it does not replace or wrap away its API.
 *
 * NOTE: this is the GoF Facade, not a Laravel `Illuminate\Support\Facades` static
 * proxy — it is an ordinary object, resolved by constructor injection.
 */
class ApplicationWorkflowFacade
{
    public function __construct(
        private readonly EligibilityService $eligibilityService,
        private readonly ApplicationService $applicationService,
        private readonly DisbursementService $disbursementService,
    ) {}

    /**
     * Step one: score the application and put it on the officer's desk.
     *
     * Running the assessment implicitly starts the review, so the two are one call.
     * Re-assessing an application already under review is a normal thing for an
     * officer to do, so that particular refusal is expected rather than an error.
     *
     * @return array the eligibility breakdown, as stored on the application
     */
    public function review(Application $application, User $admin): array
    {
        RequestContext::getInstance()->withActor($admin->id);

        $breakdown = $this->eligibilityService->assess($application);

        try {
            $this->applicationService->markUnderReview($application, $admin);
        } catch (RuntimeException) {
            // Already under review, or already decided. Neither invalidates the
            // assessment that was just recorded.
        }

        return $breakdown;
    }

    /**
     * Step two: record the decision and, when it is an approval, raise the payout.
     *
     * The decision and the payout are deliberately NOT one transaction. A recorded
     * decision is the legally meaningful act and must survive; a payout that cannot
     * be raised (an exhausted programme budget, a misconfigured payout amount) is an
     * operational problem to be fixed and retried, not a reason to un-approve a
     * beneficiary who qualified. The failure is therefore captured and reported
     * rather than rolled back.
     */
    public function close(Application $application, User $admin, bool $approved, ?string $note = null): ApplicationClosure
    {
        RequestContext::getInstance()->withActor($admin->id);

        // Throws on an already-final application; the caller reports that as-is,
        // since there is nothing partial to describe.
        $application = $this->applicationService->decide($application, $admin, $approved, $note);

        if (! $approved) {
            return new ApplicationClosure($application, approved: false);
        }

        try {
            $disbursement = $this->disbursementService->createForApplication($application, $admin);
        } catch (RuntimeException $e) {
            return new ApplicationClosure($application, approved: true, disbursementError: $e->getMessage());
        }

        return new ApplicationClosure($application, approved: true, disbursement: $disbursement);
    }
}
