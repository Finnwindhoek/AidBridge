<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Http\Controllers;

use App\Enums\DisbursementStatus;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\Disbursement;
use App\Repositories\DisbursementRepositoryInterface;
use App\Services\Disbursement\DisbursementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DisbursementController extends Controller
{
    public function __construct(
        private readonly DisbursementService $service,
        private readonly DisbursementRepositoryInterface $repository,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Disbursement::class);

        $disbursements = $this->repository->paginateFiltered([
            'status' => $request->string('status')->toString() ?: null,
            'programme' => $request->string('programme')->toString() ?: null,
            'reference' => $request->string('reference')->toString() ?: null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ]);

        return view('disbursements.index', [
            'disbursements' => $disbursements,
            'statusOptions' => DisbursementStatus::cases(),
            'programmes' => AidProgram::orderBy('title')->get(['slug', 'title']),
            'totals' => $this->repository->totalsByStatus(),
        ]);
    }

    public function show(Disbursement $disbursement): View
    {
        $this->authorize('view', $disbursement);

        $disbursement->load(['application.user', 'application.aidProgram', 'approver']);

        return view('disbursements.show', [
            'disbursement' => $disbursement,
            'state' => $disbursement->state(),
        ]);
    }

    /** Raises a pending payout against an approved application. */
    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('manage', Disbursement::class);

        try {
            $disbursement = $this->service->createForApplication($application, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['disbursement' => $e->getMessage()]);
        }

        return redirect()->route('disbursements.show', $disbursement)
            ->with('status', "Disbursement {$disbursement->reference_code} created and awaiting approval.");
    }

    public function approve(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $this->authorize('manage', Disbursement::class);

        try {
            $this->service->approve($disbursement, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['disbursement' => $e->getMessage()]);
        }

        return back()->with('status', 'Disbursement approved and budget committed.');
    }

    public function disburse(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $this->authorize('manage', Disbursement::class);

        $data = $request->validate([
            'bank_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->service->markDisbursed($disbursement, $request->user(), $data['bank_reference'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['disbursement' => $e->getMessage()]);
        }

        return back()->with('status', 'Disbursement marked as paid.');
    }

    public function reconcile(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $this->authorize('manage', Disbursement::class);

        try {
            $this->service->reconcile($disbursement, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['disbursement' => $e->getMessage()]);
        }

        return back()->with('status', 'Disbursement reconciled. This record is now closed.');
    }

    public function fail(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $this->authorize('manage', Disbursement::class);

        $data = $request->validate([
            'failure_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->service->fail($disbursement, $data['failure_reason'], $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['disbursement' => $e->getMessage()]);
        }

        return back()->with('status', 'Disbursement marked as failed and any committed budget released.');
    }
}
