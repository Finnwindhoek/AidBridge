<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\Application\ApplicationService;
use App\Services\Eligibility\EligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Module 3 entry point. Admin-only: beneficiaries see the outcome on their
 * application page but cannot trigger or re-run an assessment.
 */
class EligibilityController extends Controller
{
    public function __construct(
        private readonly EligibilityService $eligibilityService,
        private readonly ApplicationService $applicationService,
    ) {}

    /** Review queue: submitted applications awaiting assessment or decision. */
    public function queue(Request $request): View
    {
        $this->authorize('review', Application::class);

        $applications = Application::awaitingDecision()
            ->with(['user', 'aidProgram', 'documents'])
            ->orderByRaw('eligibility_score IS NULL')
            ->orderByDesc('eligibility_score')
            ->orderBy('submitted_at')
            ->paginate(15);

        return view('eligibility.queue', ['applications' => $applications]);
    }

    /** Runs the strategy chain and the external registry lookup. */
    public function assess(Application $application): RedirectResponse
    {
        $this->authorize('review', $application);

        $breakdown = $this->eligibilityService->assess($application);

        // Assessing an application implicitly starts the review.
        try {
            $this->applicationService->markUnderReview($application, auth()->user());
        } catch (RuntimeException) {
            // Already under review; re-assessment is allowed and is not an error.
        }

        $summary = $breakdown['eligible']
            ? "Assessment complete. Score {$breakdown['blended_score']}/100 — recommendation: ".
              str_replace('_', ' ', $breakdown['recommendation']).'.'
            : 'Assessment complete. The applicant does not meet the eligibility criteria.';

        return back()->with('status', $summary);
    }

    /** Records the admin's approve/reject decision. */
    public function decide(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('decide', $application);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->applicationService->decide(
                $application,
                $request->user(),
                $data['decision'] === 'approve',
                $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('status', $data['decision'] === 'approve'
            ? 'Application approved. You can now schedule the disbursement.'
            : 'Application rejected and the applicant has been notified.');
    }
}
