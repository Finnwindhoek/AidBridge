{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Module 3 — Verification & Eligibility Assessment
    Author: Chia Yi Kuang
--}}
@extends('layouts.app')
@section('title', 'Review Queue')

@section('content')
<x-page-header
    title="Review queue"
    subtitle="Submitted applications awaiting assessment or a decision, highest priority score first. Unassessed applications are listed at the top.">
    <x-slot:actions>
        <span class="badge text-bg-secondary align-self-center">
            {{ $applications->total() }} {{ Str::plural('application', $applications->total()) }} waiting
        </span>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <caption class="visually-hidden">Applications awaiting eligibility assessment or a decision</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Applicant</th>
                    <th scope="col">Programme</th>
                    <th scope="col" class="text-end">Income</th>
                    <th scope="col" class="text-center">Deps</th>
                    <th scope="col" class="text-center">Docs</th>
                    <th scope="col" class="text-center">Score</th>
                    <th scope="col">Status</th>
                    <th scope="col">Waiting</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($applications as $application)
                @php
                    $breakdown = $application->eligibility_breakdown;
                    $totalDocs = $application->documents->count();
                    $verified = $application->documents->whereNotNull('verified_at')->count();
                    $allVerified = $totalDocs > 0 && $verified === $totalDocs;
                    $flagged = $breakdown['flagged_for_review'] ?? false;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('applications.show', $application) }}" class="fw-semibold text-decoration-none">
                            {{ $application->user->name }}
                        </a>
                        <div class="text-muted small">{{ $application->state ?: '—' }}</div>
                    </td>

                    <td>
                        {{ $application->aidProgram->title }}
                        <div class="text-muted small">{{ $application->aidProgram->type->label() }}</div>
                    </td>

                    <td class="text-end">RM {{ number_format((float) $application->household_income, 2) }}</td>
                    <td class="text-center">{{ $application->dependents_count }}</td>

                    <td class="text-center">
                        <span class="badge text-bg-{{ $allVerified ? 'success' : 'secondary' }}"
                              title="{{ $verified }} of {{ $totalDocs }} documents verified">
                            <i class="bi bi-{{ $allVerified ? 'check2' : 'paperclip' }}" aria-hidden="true"></i>
                            {{ $verified }}/{{ $totalDocs }}
                        </span>
                    </td>

                    <td class="text-center">
                        @if ($application->eligibility_score !== null)
                            <span class="badge text-bg-{{ $application->eligibility_score >= 50 ? 'success' : 'warning' }}">
                                {{ $application->eligibility_score }}
                            </span>
                            @if ($flagged)
                                <i class="bi bi-flag-fill text-warning ms-1"
                                   title="Registry discrepancy — needs manual review"></i>
                                <span class="visually-hidden">Flagged: registry discrepancy</span>
                            @endif
                        @else
                            <span class="text-muted small">Not assessed</span>
                        @endif
                    </td>

                    <td><x-status-badge :status="$application->status" /></td>

                    <td class="small text-muted">
                        {{ $application->submitted_at?->diffForHumans(short: true) ?? '—' }}
                    </td>

                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <form method="POST" action="{{ route('eligibility.assess', $application) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary"
                                        title="Run eligibility assessment">
                                    <i class="bi bi-calculator" aria-hidden="true"></i>
                                    <span class="visually-hidden">Assess {{ $application->user->name }}</span>
                                </button>
                            </form>
                            <a href="{{ route('applications.show', $application) }}"
                               class="btn btn-sm btn-outline-secondary">Review</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        <x-empty-state
                            icon="check2-circle"
                            title="The queue is clear"
                            message="Nothing is awaiting assessment or a decision right now." />
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
