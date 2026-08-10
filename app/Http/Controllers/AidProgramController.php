<?php

namespace App\Http\Controllers;

use App\Enums\AidProgramStatus;
use App\Http\Requests\AidProgramRequest;
use App\Models\AidProgram;
use App\Services\AidProgram\AidProgramFactory;
use App\Services\AidProgram\AidProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AidProgramController extends Controller
{
    public function __construct(
        private readonly AidProgramService $service,
        private readonly AidProgramFactory $factory,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $programs = AidProgram::query()
            // Beneficiaries only ever see live programmes.
            ->when(! $user->isAdmin(), fn ($q) => $q->acceptingApplications())
            ->when($request->filled('status') && $user->isAdmin(),
                fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'),
                fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('q'), function ($q) use ($request) {
                // Bound parameter, never string interpolation.
                $term = '%'.$request->string('q').'%';
                $q->where('title', 'like', $term);
            })
            ->withCount('applications')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('aid_programs.index', [
            'programs' => $programs,
            'typeOptions' => $this->factory->options(),
            'statusOptions' => AidProgramStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AidProgram::class);

        return view('aid_programs.create', [
            'typeOptions' => $this->factory->options(),
            // Lets the form prefill sensible values the moment a type is chosen.
            'typeDefaults' => collect($this->factory->options())
                ->mapWithKeys(fn ($label, $value) => [$value => $this->factory->defaultsFor($value)]),
        ]);
    }

    public function store(AidProgramRequest $request): RedirectResponse
    {
        $this->authorize('create', AidProgram::class);

        $program = $this->service->create($request->validated(), $request->user()->id);

        return redirect()->route('aid-programs.show', $program)
            ->with('status', "Programme \"{$program->title}\" created.");
    }

    public function show(AidProgram $aidProgram): View
    {
        $this->authorize('view', $aidProgram);

        $aidProgram->loadCount('applications');

        return view('aid_programs.show', [
            'program' => $aidProgram,
            'config' => $this->factory->forProgram($aidProgram),
            'requiredDocuments' => $this->service->requiredDocumentsFor($aidProgram),
        ]);
    }

    public function edit(AidProgram $aidProgram): View
    {
        $this->authorize('update', $aidProgram);

        return view('aid_programs.edit', [
            'program' => $aidProgram,
            'statusOptions' => AidProgramStatus::cases(),
        ]);
    }

    public function update(AidProgramRequest $request, AidProgram $aidProgram): RedirectResponse
    {
        $this->authorize('update', $aidProgram);

        try {
            $this->service->update($aidProgram, $request->validated());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['budget_allocated' => $e->getMessage()]);
        }

        return redirect()->route('aid-programs.show', $aidProgram)
            ->with('status', 'Programme updated.');
    }

    public function destroy(AidProgram $aidProgram): RedirectResponse
    {
        $this->authorize('delete', $aidProgram);

        try {
            $this->service->delete($aidProgram);
        } catch (RuntimeException $e) {
            return back()->withErrors(['programme' => $e->getMessage()]);
        }

        return redirect()->route('aid-programs.index')->with('status', 'Programme deleted.');
    }

    public function archive(AidProgram $aidProgram): RedirectResponse
    {
        $this->authorize('update', $aidProgram);

        $this->service->archive($aidProgram);

        return back()->with('status', 'Programme archived.');
    }
}
