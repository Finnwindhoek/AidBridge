@extends('layouts.app')
@section('title', 'Review Queue')

@section('content')
<h1 class="h4 mb-1">Review queue</h1>
<p class="text-muted small mb-3">
    Submitted applications awaiting assessment or a decision, highest priority score first.
    Unassessed applications are listed at the top.
</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Applicant</th>
                    <th>Programme</th>
                    <th class="text-end">Income</th>
                    <th class="text-center">Deps</th>
                    <th class="text-center">Docs</th>
                    <th class="text-center">Score</th>
                    <th>Status</th>
                    <th>Waiting</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($applications as $application)
                @php
                    $breakdown = $application->eligibility_breakdown;
                    $verified = $application->documents->whereNotNull('verified_at')->count();
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('applications.show', $application) }}" class="fw-semibold text-decoration-none">
                            {{ $application->user->name }}
                        </a>
                        <div class="text-muted small">{{ $application->state }}</div>
                    </td>
                    <td>
                        {{ $application->aidProgram->title }}
                        <div class="text-muted small">{{ $application->aidProgram->type->label() }}</div>
                    </td>
                    <td class="text-end">RM {{ number_format((float) $application->household_income, 2) }}</td>
                    <td class="text-center">{{ $application->dependents_count }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $verified === $application->documents->count() && $verified > 0 ? 'success' : 'secondary' }}">
                            {{ $verified }}/{{ $application->documents->count() }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if ($application->eligibility_score !== null)
                            <span class="badge bg-{{ $application->eligibility_score >= 50 ? 'success' : 'warning text-dark' }}">
                                {{ $application->eligibility_score }}
                            </span>
                            @if ($breakdown['flagged_for_review'] ?? false)
                                <i class="bi bi-flag-fill text-warning" title="Registry discrepancy"></i>
                            @endif
                        @else
                            <span class="text-muted">Not assessed</span>
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $application->status->colour() }}">{{ $application->status->label() }}</span></td>
                    <td class="small text-muted">
                        {{ $application->submitted_at?->diffForHumans(short: true) ?? '—' }}
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <form method="POST" action="{{ route('eligibility.assess', $application) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary" title="Run assessment">
                                    <i class="bi bi-calculator"></i>
                                </button>
                            </form>
                            <a href="{{ route('applications.show', $application) }}" class="btn btn-sm btn-outline-secondary">
                                Review
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-check2-circle fs-4 d-block mb-2"></i>
                        The queue is clear — nothing is awaiting a decision.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $applications->links() }}</div>
@endsection
