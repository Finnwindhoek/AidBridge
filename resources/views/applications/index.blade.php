{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Module 2 — Application & Document Management
    Author: Lee Kar How
--}}
@extends('layouts.app')
@section('title', 'Applications')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

<x-page-header
    :title="$isAdmin ? 'All applications' : 'My applications'"
    subtitle="Track status from submission through to payment.">
    @can('create', App\Models\Application::class)
        <x-slot:actions>
            <a href="{{ route('applications.create') }}" class="btn btn-aidbridge">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> New application
            </a>
        </x-slot:actions>
    @endcan
</x-page-header>

<x-filter-card
    :action="route('applications.index')"
    :count="$applications->total().' '.Str::plural('application', $applications->total()).' found'">

    <div class="col-md-4">
        <label for="filter-status" class="form-label small mb-1">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">All statuses</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-5">
        <label for="filter-programme" class="form-label small mb-1">Programme</label>
        <select id="filter-programme" name="programme" class="form-select form-select-sm">
            <option value="">All programmes</option>
            @foreach ($programmes as $programme)
                <option value="{{ $programme->slug }}" @selected(request('programme') === $programme->slug)>
                    {{ $programme->title }}
                </option>
            @endforeach
        </select>
    </div>
</x-filter-card>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <caption class="visually-hidden">List of aid applications with status and payment progress</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Reference</th>
                    @if ($isAdmin)<th scope="col">Applicant</th>@endif
                    <th scope="col">Programme</th>
                    <th scope="col" class="text-end">Income</th>
                    <th scope="col" class="text-center">Deps</th>
                    <th scope="col" class="text-center">Score</th>
                    <th scope="col">Status</th>
                    <th scope="col">Payment</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($applications as $application)
                <tr>
                    <td>
                        <a href="{{ route('applications.show', $application) }}" class="mono fw-semibold text-decoration-none">
                            {{ Str::limit($application->reference, 13, '…') }}
                        </a>
                        <div class="text-muted small">{{ $application->created_at->format('d M Y') }}</div>
                    </td>

                    @if ($isAdmin)
                        <td>
                            {{ $application->user->name }}
                            <div class="text-muted small">{{ $application->state ?: '—' }}</div>
                        </td>
                    @endif

                    <td>{{ $application->aidProgram->title }}</td>
                    <td class="text-end">RM {{ number_format((float) $application->household_income, 2) }}</td>
                    <td class="text-center">{{ $application->dependents_count }}</td>

                    <td class="text-center">
                        @if ($application->eligibility_score !== null)
                            <span class="badge text-bg-{{ $application->eligibility_score >= 50 ? 'success' : 'secondary' }}">
                                {{ $application->eligibility_score }}
                            </span>
                        @else
                            <span class="text-muted" title="Not yet assessed">—</span>
                        @endif
                    </td>

                    <td><x-status-badge :status="$application->status" /></td>

                    <td>
                        @if ($application->disbursement)
                            <x-status-badge :status="$application->disbursement->status" />
                            <div class="small text-muted mt-1">
                                RM {{ number_format((float) $application->disbursement->amount, 2) }}
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 8 : 7 }}">
                        <x-empty-state
                            icon="file-earmark-text"
                            title="No applications found"
                            :message="request()->hasAny(['status', 'programme'])
                                ? 'No applications match these filters.'
                                : 'Applications will appear here once they are created.'">
                            @can('create', App\Models\Application::class)
                                <x-slot:action>
                                    <a href="{{ route('applications.create') }}" class="btn btn-sm btn-aidbridge">
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Start an application
                                    </a>
                                </x-slot:action>
                            @endcan
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($applications->hasPages())
    <div class="mt-3">{{ $applications->withQueryString()->links() }}</div>
@endif
@endsection
