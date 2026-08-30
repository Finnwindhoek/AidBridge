@extends('layouts.app')
@section('title', 'Application Reports')

@section('content')
<x-page-header
    title="Application reports"
    subtitle="Filters are assembled by ApplicationReportBuilder into a single bound query.">
    <x-slot:actions>
        {{-- Exports carry the current filters through to the download. --}}
        <a href="{{ route('reports.export.csv', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV
        </a>
        <a href="{{ route('reports.export.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-filetype-pdf" aria-hidden="true"></i> Export PDF
        </a>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer" aria-hidden="true"></i> Print
        </button>
    </x-slot:actions>
</x-page-header>

<div class="card mb-3 no-print">
    <div class="card-body py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label for="rep-status" class="form-label small mb-1">Status</label>
                <select id="rep-status" name="status" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="rep-programme" class="form-label small mb-1">Programme</label>
                <select id="rep-programme" name="programme" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($programmes as $programme)
                        <option value="{{ $programme->slug }}" @selected(($filters['programme'] ?? null) === $programme->slug)>
                            {{ $programme->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="rep-programme_type" class="form-label small mb-1">Programme type</label>
                <select id="rep-programme_type" name="programme_type" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($typeOptions as $type)
                        <option value="{{ $type->value }}" @selected(($filters['programme_type'] ?? null) === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="rep-state" class="form-label small mb-1">State</label>
                <select id="rep-state" name="state" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach ($states as $state)
                        <option value="{{ $state }}" @selected(($filters['state'] ?? null) === $state)>{{ $state }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label for="rep-min_dependents" class="form-label small mb-1">Min dependents</label>
                <input type="number" min="0" max="20" id="rep-min_dependents" name="min_dependents"
                       value="{{ $filters['min_dependents'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="rep-max_income" class="form-label small mb-1">Max income (RM)</label>
                <input type="number" step="0.01" min="0" id="rep-max_income" name="max_income"
                       value="{{ $filters['max_income'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="rep-min_score" class="form-label small mb-1">Min score</label>
                <input type="number" min="0" max="100" id="rep-min_score" name="min_score"
                       value="{{ $filters['min_score'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="rep-decided_from" class="form-label small mb-1">Decided from</label>
                <input type="date" id="rep-decided_from" name="decided_from" value="{{ $filters['decided_from'] ?? '' }}"
                       class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="rep-decided_to" class="form-label small mb-1">Decided to</label>
                <input type="date" id="rep-decided_to" name="decided_to" value="{{ $filters['decided_to'] ?? '' }}"
                       class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="rep-sort" class="form-label small mb-1">Sort by</label>
                <select id="rep-sort" name="sort" class="form-select form-select-sm">
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
                <button type="submit" class="btn btn-sm btn-aidbridge"><i class="bi bi-play-fill" aria-hidden="true"></i> Run report</button>
                <a href="{{ route('reports.applications') }}" class="btn btn-sm btn-outline-secondary">Clear filters</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary band over the filtered set --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-stat-card label="Matching applications" icon="funnel"
                     :value="number_format($summary['total'])" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-card label="Average income" icon="cash"
                     :value="'RM '.number_format($summary['avg_income'], 0)" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-card label="Average dependents" icon="people"
                     :value="$summary['avg_dependents']" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-card label="Average score" icon="speedometer2"
                     :value="$summary['avg_score']" />
    </div>
</div>

@if ($appliedFilters)
    <p class="small text-muted">
        <strong>Filters applied:</strong>
        @foreach ($appliedFilters as $label => $value)
            <span class="badge badge-soft">{{ $label }}: {{ $value }}</span>
        @endforeach
    </p>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Applicant</th>
                    <th scope="col">Programme</th>
                    <th scope="col">State</th>
                    <th scope="col" class="text-end">Income</th>
                    <th scope="col" class="text-center">Deps</th>
                    <th scope="col" class="text-center">Score</th>
                    <th scope="col">Status</th>
                    <th scope="col">Decided</th>
                    <th scope="col" class="text-end">Paid</th>
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
                    <td class="small">{{ $application->state ?: '—' }}</td>
                    <td class="text-end">{{ number_format((float) $application->household_income, 2) }}</td>
                    <td class="text-center">{{ $application->dependents_count }}</td>
                    <td class="text-center">{{ $application->eligibility_score ?? '—' }}</td>
                    <td><x-status-badge :status="$application->status" /></td>
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
                <tr>
                    <td colspan="10">
                        <x-empty-state
                            icon="funnel"
                            title="No applications match these filters"
                            message="Widen the criteria above and run the report again." />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($applications->hasPages())
    <div class="mt-3 no-print">{{ $applications->withQueryString()->links() }}</div>
@endif
@endsection
