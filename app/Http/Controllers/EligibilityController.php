<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\Workflow\ApplicationWorkflowFacade;
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
    /**
     * One collaborator, not three: the FACADE owns the ordering of the assessment,
     * the status moves and the payout so this controller does not have to.
     */
    public function __construct(
        private readonly ApplicationWorkflowFacade $workflow,
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
    public function assess(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('review', $application);

        $breakdown = $this->workflow->review($application, $request->user());

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
            $closure = $this->workflow->close(
                $application,
                $request->user(),
                $data['decision'] === 'approve',
                $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        // An approval whose payout could not be raised is still an approval, so it
        // is reported as a warning beside the decision rather than as a failure.
        return $closure->needsManualDisbursement()
            ? back()->with('warning', $closure->summary())
            : back()->with('status', $closure->summary());
    }
}
