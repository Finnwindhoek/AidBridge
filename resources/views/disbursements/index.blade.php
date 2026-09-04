{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Module 4 — Fund Allocation & Disbursement Tracking
    Author: Kartik
--}}
@extends('layouts.app')
@section('title', 'Disbursements')

@section('content')
<x-page-header
    title="Disbursements"
    subtitle="The financial ledger. Every state change is atomic and audit-logged." />

{{-- One tile per lifecycle state, so the pipeline is visible at a glance. --}}
<div class="row g-3 mb-3">
    @foreach ($statusOptions as $status)
        @php $row = $totals[$status->value] ?? null; @endphp
        <div class="col-6 col-lg">
            <x-stat-card
                :label="$status->label()"
                :icon="$status->icon()"
                :value="number_format($row->count ?? 0)"
                :value-class="'text-'.$status->colour()"
                :meta="'RM '.number_format((float) ($row->total ?? 0), 2)" />
        </div>
    @endforeach
</div>

<x-filter-card
    :action="route('disbursements.index')"
    :count="$disbursements->total().' '.Str::plural('disbursement', $disbursements->total()).' found'">

    <div class="col-md-2">
        <label for="filter-status" class="form-label small mb-1">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
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

    <div class="col-md-2">
        <label for="filter-reference" class="form-label small mb-1">Reference</label>
        <input type="search" id="filter-reference" name="reference" value="{{ request('reference') }}"
               class="form-control form-control-sm" placeholder="AB-2024…">
    </div>

    <div class="col-md-2">
        <label for="filter-from" class="form-label small mb-1">From</label>
        <input type="date" id="filter-from" name="from" value="{{ request('from') }}"
               class="form-control form-control-sm">
    </div>

    <div class="col-md-2">
        <label for="filter-to" class="form-label small mb-1">To</label>
        <input type="date" id="filter-to" name="to" value="{{ request('to') }}"
               class="form-control form-control-sm">
    </div>
</x-filter-card>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <caption class="visually-hidden">Disbursement ledger with status and next permitted step</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Beneficiary</th>
                    <th scope="col">Programme</th>
                    <th scope="col" class="text-end">Amount</th>
                    <th scope="col">Status</th>
                    <th scope="col">Next step</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($disbursements as $disbursement)
                @php $state = $disbursement->state(); @endphp
                <tr>
                    <td>
                        <a href="{{ route('disbursements.show', $disbursement) }}" class="mono fw-semibold text-decoration-none">
                            {{ $disbursement->reference_code }}
                        </a>
                        <div class="text-muted small">{{ $disbursement->created_at->format('d M Y') }}</div>
                    </td>

                    <td>{{ $disbursement->application->user->name }}</td>
                    <td>{{ $disbursement->application->aidProgram->title }}</td>
                    <td class="text-end fw-semibold">RM {{ number_format((float) $disbursement->amount, 2) }}</td>
                    <td><x-status-badge :status="$disbursement->status" /></td>

                    <td class="small">
                        {{-- Allowed transitions come straight from the State object. --}}
                        @forelse ($state->allowedTransitions() as $next)
                            <span class="badge badge-soft">{{ $next->label() }}</span>
                        @empty
                            <span class="text-muted">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i> Closed
                            </span>
                        @endforelse
                    </td>

                    <td class="text-end">
                        <a href="{{ route('disbursements.show', $disbursement) }}"
                           class="btn btn-sm btn-outline-secondary">Manage</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state
                            icon="cash-coin"
                            title="No disbursements found"
                            :message="request()->hasAny(['status', 'programme', 'reference', 'from', 'to'])
                                ? 'No disbursements match these filters.'
                                : 'Payouts appear here once an approved application is scheduled for payment.'" />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($disbursements->hasPages())
    <div class="mt-3">{{ $disbursements->withQueryString()->links() }}</div>
@endif
@endsection
