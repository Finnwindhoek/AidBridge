@extends('layouts.app')
@section('title', 'Application Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Application reports</h1>
        <p class="text-muted small mb-0">
            Filters are assembled by <span class="mono">ApplicationReportBuilder</span> into a single bound query.
        </p>
    </div>
    <div class="d-flex gap-2">
        {{-- Exports carry the current filters through to the download. --}}
        <a href="{{ route('reports.export.csv', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-filetype-csv"></i> Export CSV
        </a>
        <a href="{{ route('reports.export.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-filetype-pdf"></i> Export PDF
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Programme</label>
                <select name="programme" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($programmes as $programme)
                        <option value="{{ $programme->slug }}" @selected(($filters['programme'] ?? null) === $programme->slug)>
                            {{ $programme->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Programme type</label>
                <select name="programme_type" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($typeOptions as $type)
                        <option value="{{ $type->value }}" @selected(($filters['programme_type'] ?? null) === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">State</label>
                <select name="state" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($states as $state)
                        <option value="{{ $state }}" @selected(($filters['state'] ?? null) === $state)>{{ $state }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small mb-1">Min dependents</label>
                <input type="number" min="0" max="20" name="min_dependents"
                       value="{{ $filters['min_dependents'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Max income (RM)</label>
                <input type="number" step="0.01" min="0" name="max_income"
                       value="{{ $filters['max_income'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Min score</label>
                <input type="number" min="0" max="100" name="min_score"
                       value="{{ $filters['min_score'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Decided from</label>
                <input type="date" name="decided_from" value="{{ $filters['decided_from'] ?? '' }}"
                       class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Decided to</label>
                <input type="date" name="decided_to" value="{{ $filters['decided_to'] ?? '' }}"
                       class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Sort by</label>
                <select name="sort" class="form-select form-select-sm">
                    @foreach (['created_at' => 'Created', 'submitted_at' => 'Submitted', 'decided_at' => 'Decided',
                               'eligibility_score' => 'Score', 'household_income' => 'Income',
                               'dependents_count' => 'Dependents'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['sort'] ?? 'created_at') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 d-flex align-items-center gap-3 pt-1">
                <div class="form-check">
                    <input type="checkbox" name="funded_only" value="1" id="funded_only"
                           class="form-check-input" @checked($filters['funded_only'] ?? false)>
                    <label for="funded_only" class="form-check-label small">Funded applications only</label>
                </div>
                <button class="btn btn-sm btn-aidbridge">Run report</button>
                <a href="{{ route('reports.applications') }}" class="btn btn-sm btn-outline-secondary">Clear filters</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary band over the filtered set --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="small text-muted">Matching applications</div>
            <div class="stat-value">{{ number_format($summary['total']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="small text-muted">Average income</div>
            <div class="stat-value">RM {{ number_format($summary['avg_income'], 0) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="small text-muted">Average dependents</div>
            <div class="stat-value">{{ $summary['avg_dependents'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="small text-muted">Average score</div>
            <div class="stat-value">{{ $summary['avg_score'] }}</div>
        </div></div>
    </div>
</div>

@if ($appliedFilters)
    <p class="small text-muted">
        <strong>Filters applied:</strong>
        @foreach ($appliedFilters as $label => $value)
            <span class="badge bg-light text-dark">{{ $label }}: {{ $value }}</span>
        @endforeach
    </p>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    <th>Applicant</th>
                    <th>Programme</th>
                    <th>State</th>
                    <th class="text-end">Income</th>
                    <th class="text-center">Deps</th>
                    <th class="text-center">Score</th>
                    <th>Status</th>
                    <th>Decided</th>
                    <th class="text-end">Paid</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($applications as $application)
                <tr>
                    <td>
                        <a href="{{ route('applications.show', $application) }}" class="mono text-decoration-none">
                            {{ Str::limit($application->reference, 10, '…') }}
                        </a>
                    </td>
                    <td>{{ $application->user->name }}</td>
                    <td class="small">{{ $application->aidProgram->title }}</td>
                    <td class="small">{{ $application->state }}</td>
                    <td class="text-end">{{ number_format((float) $application->household_income, 2) }}</td>
                    <td class="text-center">{{ $application->dependents_count }}</td>
                    <td class="text-center">{{ $application->eligibility_score ?? '—' }}</td>
                    <td><span class="badge bg-{{ $application->status->colour() }}">{{ $application->status->label() }}</span></td>
                    <td class="small">{{ $application->decided_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end">
                        @if ($application->disbursement)
                            RM {{ number_format((float) $application->disbursement->amount, 2) }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No applications match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $applications->links() }}</div>
@endsection
