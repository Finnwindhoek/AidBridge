<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\ApplicationRequest;
use App\Models\AidProgram;
use App\Models\Application;
use App\Services\Application\ApplicationService;
use App\Services\Eligibility\EligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $service,
        private readonly EligibilityService $eligibilityService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $applications = Application::query()
            // The core tenancy guard: a beneficiary's query is scoped to their own
            // rows before any filter is applied.
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->status($request->string('status')->toString()))
            ->when($request->filled('programme'), fn ($q) => $q->whereHas(
                'aidProgram',
                fn ($p) => $p->where('slug', $request->string('programme'))
            ))
            ->with(['aidProgram', 'user', 'disbursement'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('applications.index', [
            'applications' => $applications,
            'statusOptions' => ApplicationStatus::cases(),
            'programmes' => AidProgram::orderBy('title')->get(['slug', 'title']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Application::class);

        $user = $request->user();

        // Programmes the user has not already applied to.
        $programmes = AidProgram::acceptingApplications()
            ->whereDoesntHave('applications', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('title')
            ->get();

        return view('applications.create', [
            'programmes' => $programmes,
            'selected' => $request->string('programme')->toString(),
        ]);
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        $program = AidProgram::where('slug', $request->validated('aid_program_slug'))->firstOrFail();

        try {
            $application = $this->service->create(
                $request->user(),
                $program,
                $request->applicationData()
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['aid_program_slug' => $e->getMessage()]);
        }

        return redirect()->route('applications.show', $application)
            ->with('status', 'Draft created. Upload your supporting documents, then submit.');
    }

    public function show(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load(['aidProgram', 'documents', 'user', 'disbursement', 'decider']);

        return view('applications.show', [
            'application' => $application,
            'requiredDocuments' => $this->eligibilityService->requiredDocumentsFor($application),
            // The audit trail is an admin-only view of the record.
            'auditTrail' => auth()->user()->isAdmin()
                ? $application->auditLogs()->with('user')->latest()->limit(25)->get()
                : collect(),
        ]);
    }

    public function edit(Application $application): View
    {
        $this->authorize('update', $application);

        return view('applications.edit', ['application' => $application->load('aidProgram')]);
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        try {
            $this->service->update($application, $request->applicationData());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['application' => $e->getMessage()]);
        }

        return redirect()->route('applications.show', $application)->with('status', 'Application updated.');
    }

    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        $application->delete();

        return redirect()->route('applications.index')->with('status', 'Draft application deleted.');
    }

    public function submit(Application $application): RedirectResponse
    {
        $this->authorize('submit', $application);

        try {
            $this->service->submit($application);
        } catch (RuntimeException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return redirect()->route('applications.show', $application)
            ->with('status', 'Application submitted. You will be notified once it has been reviewed.');
    }

    public function withdraw(Application $application): RedirectResponse
    {
        $this->authorize('withdraw', $application);

        try {
            $this->service->withdraw($application);
        } catch (RuntimeException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('status', 'Application withdrawn.');
    }
}
