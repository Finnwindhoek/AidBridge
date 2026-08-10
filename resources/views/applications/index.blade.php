@extends('layouts.app')
@section('title', 'Applications')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">{{ auth()->user()->isAdmin() ? 'All Applications' : 'My Applications' }}</h1>
        <p class="text-muted small mb-0">Track status from submission through to payment.</p>
    </div>
    @can('create', App\Models\Application::class)
        <a href="{{ route('applications.create') }}" class="btn btn-aidbridge">
            <i class="bi bi-plus-lg"></i> New application
        </a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1">Programme</label>
                <select name="programme" class="form-select form-select-sm">
                    <option value="">All programmes</option>
                    @foreach ($programmes as $programme)
                        <option value="{{ $programme->slug }}" @selected(request('programme') === $programme->slug)>
                            {{ $programme->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-secondary flex-grow-1">Filter</button>
                <a href="{{ route('applications.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    @if (auth()->user()->isAdmin())<th>Applicant</th>@endif
                    <th>Programme</th>
                    <th class="text-end">Income</th>
                    <th class="text-center">Deps</th>
                    <th class="text-center">Score</th>
                    <th>Status</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($applications as $application)
                <tr>
                    <td>
                        <a href="{{ route('applications.show', $application) }}" class="mono text-decoration-none">
                            {{ Str::limit($application->reference, 13, '…') }}
                        </a>
                        <div class="text-muted small">{{ $application->created_at->format('d M Y') }}</div>
                    </td>
                    @if (auth()->user()->isAdmin())
                        <td>
                            {{ $application->user->name }}
                            <div class="text-muted small">{{ $application->state }}</div>
                        </td>
                    @endif
                    <td>{{ $application->aidProgram->title }}</td>
                    <td class="text-end">RM {{ number_format((float) $application->household_income, 2) }}</td>
                    <td class="text-center">{{ $application->dependents_count }}</td>
                    <td class="text-center">
                        @if ($application->eligibility_score !== null)
                            <span class="badge bg-{{ $application->eligibility_score >= 50 ? 'success' : 'secondary' }}">
                                {{ $application->eligibility_score }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $application->status->colour() }}">{{ $application->status->label() }}</span></td>
                    <td>
                        @if ($application->disbursement)
                            <span class="badge bg-{{ $application->disbursement->status->colour() }}">
                                {{ $application->disbursement->status->label() }}
                            </span>
                            <div class="small text-muted">
                                RM {{ number_format((float) $application->disbursement->amount, 2) }}
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No applications yet.
                        @can('create', App\Models\Application::class)
                            <a href="{{ route('applications.create') }}">Start one.</a>
                        @endcan
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $applications->links() }}</div>
@endsection
