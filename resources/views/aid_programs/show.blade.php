@extends('layouts.app')
@section('title', $program->title)

@section('content')
@php $used = (float) $program->budget_used_percent; @endphp

<x-page-header
    :title="$program->title"
    :breadcrumbs="[
        ['label' => 'Programmes', 'url' => route('aid-programs.index')],
        ['label' => $program->title],
    ]">

    <x-slot:actions>
        @can('update', $program)
            <a href="{{ route('aid-programs.edit', $program) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil" aria-hidden="true"></i> Edit
            </a>
        @endcan

        @if ($program->isAcceptingApplications() && auth()->user()->isBeneficiary())
            <a href="{{ route('applications.create', ['programme' => $program->slug]) }}" class="btn btn-aidbridge btn-sm">
                <i class="bi bi-pencil-square" aria-hidden="true"></i> Apply for this programme
            </a>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="mb-3 d-flex flex-wrap gap-1">
    <x-status-badge :status="$program->status" />
    <span class="badge badge-soft">
        <i class="bi bi-{{ $program->type->icon() }}" aria-hidden="true"></i> {{ $program->type->label() }}
    </span>
    @unless ($program->isAcceptingApplications())
        <span class="badge text-bg-secondary">Not accepting applications</span>
    @endunless
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle" aria-hidden="true"></i> About this programme</div>
            <div class="card-body">
                <p class="mb-0">{{ $program->description ?: 'No description provided.' }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-list-check" aria-hidden="true"></i> Eligibility &amp; payout rules</div>
            <div class="card-body">
                <dl class="row mb-0 detail-list">
                    <dt class="col-sm-5">Income threshold</dt>
                    <dd class="col-sm-7">RM {{ number_format((float) $program->income_threshold, 2) }} <span class="text-muted">per month</span></dd>

                    <dt class="col-sm-5">Minimum dependents</dt>
                    <dd class="col-sm-7">{{ $program->min_dependents }}</dd>

                    <dt class="col-sm-5">Base payout</dt>
                    <dd class="col-sm-7">RM {{ number_format((float) $program->payout_amount, 2) }}</dd>

                    <dt class="col-sm-5">Payout rule</dt>
                    <dd class="col-sm-7">
                        {{-- Behaviour supplied by the concrete Factory product. --}}
                        @switch($program->type->value)
                            @case('cash_disbursement') Base amount plus RM 50 per dependent, up to 5. @break
                            @case('voucher') Base amount plus RM 50 per dependent, rounded down to whole vouchers. @break
                            @case('emergency_grant') Base amount scaled 1.0x–1.5x by eligibility score. @break
                        @endswitch
                    </dd>

                    <dt class="col-sm-5">Required documents</dt>
                    <dd class="col-sm-7">
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($requiredDocuments as $doc)
                                <span class="badge badge-soft">{{ ucwords(str_replace('_', ' ', $doc)) }}</span>
                            @endforeach
                        </div>
                    </dd>

                    <dt class="col-sm-5">Application window</dt>
                    <dd class="col-sm-7">
                        {{ $program->opens_at?->format('d M Y') ?? 'Immediately' }} &mdash;
                        {{ $program->closes_at?->format('d M Y') ?? 'No closing date' }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-wallet2" aria-hidden="true"></i> Budget</div>
            <div class="card-body">
                <div class="stat-label">Remaining</div>
                <div class="stat-value text-aidbridge mb-1">
                    RM {{ number_format((float) $program->budget_remaining, 2) }}
                </div>
                <p class="text-muted small">of RM {{ number_format((float) $program->budget_allocated, 2) }} allocated</p>

                @php $barColour = $used >= 90 ? 'bg-danger' : ($used >= 70 ? 'bg-warning' : 'bg-aidbridge'); @endphp
                <div class="progress" style="height:8px"
                     role="progressbar" aria-label="Budget committed"
                     aria-valuenow="{{ round($used) }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar {{ $barColour }}" style="width: {{ $used }}%"></div>
                </div>
                <p class="small text-muted mt-2 mb-0">{{ $used }}% committed</p>
            </div>
        </div>

        @if (auth()->user()->isAdmin())
            <div class="card">
                <div class="card-header"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Applications</div>
                <div class="card-body">
                    <div class="stat-value">{{ $program->applications_count }}</div>
                    <a href="{{ route('applications.index', ['programme' => $program->slug]) }}"
                       class="small text-decoration-none">
                        View all applications <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
