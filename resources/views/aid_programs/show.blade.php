@extends('layouts.app')
@section('title', $program->title)

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1">{{ $program->title }}</h1>
        <span class="badge bg-{{ $program->status->colour() }}">{{ $program->status->label() }}</span>
        <span class="badge bg-light text-dark">{{ $program->type->label() }}</span>
    </div>
    <div class="d-flex gap-2">
        @can('update', $program)
            <a href="{{ route('aid-programs.edit', $program) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Edit
            </a>
        @endcan
        @if ($program->isAcceptingApplications() && auth()->user()->isBeneficiary())
            <a href="{{ route('applications.create', ['programme' => $program->slug]) }}" class="btn btn-aidbridge">
                Apply for this programme
            </a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-2">About this programme</h2>
                <p class="mb-0">{{ $program->description ?: 'No description provided.' }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">Eligibility &amp; payout rules</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-5">Income threshold</dt>
                    <dd class="col-sm-7">RM {{ number_format((float) $program->income_threshold, 2) }} per month</dd>

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
                        @foreach ($requiredDocuments as $doc)
                            <span class="badge bg-secondary-subtle text-dark">
                                {{ ucwords(str_replace('_', ' ', $doc)) }}
                            </span>
                        @endforeach
                    </dd>

                    <dt class="col-sm-5">Application window</dt>
                    <dd class="col-sm-7">
                        {{ $program->opens_at?->format('d M Y') ?? 'Immediately' }} —
                        {{ $program->closes_at?->format('d M Y') ?? 'No closing date' }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">Budget</h2>
                <div class="stat-value text-aidbridge mb-1">
                    RM {{ number_format((float) $program->budget_remaining, 2) }}
                </div>
                <p class="text-muted small">remaining of RM {{ number_format((float) $program->budget_allocated, 2) }}</p>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-aidbridge" style="width: {{ $program->budget_used_percent }}%"></div>
                </div>
                <p class="small text-muted mt-2 mb-0">{{ $program->budget_used_percent }}% committed</p>
            </div>
        </div>

        @if (auth()->user()->isAdmin())
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Applications</h2>
                    <div class="stat-value">{{ $program->applications_count }}</div>
                    <a href="{{ route('applications.index', ['programme' => $program->slug]) }}"
                       class="small text-decoration-none">View all applications →</a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
