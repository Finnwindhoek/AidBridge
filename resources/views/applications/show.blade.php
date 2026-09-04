{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Module 2 — Application & Document Management
    Author: Lee Kar How
--}}
@extends('layouts.app')
@section('title', 'Application ' . Str::limit($application->reference, 8, ''))

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $breakdown = $application->eligibility_breakdown;
    $verification = $application->agency_verification;
    $status = $application->status;
@endphp

<x-page-header
    :title="$application->aidProgram->title"
    :breadcrumbs="[
        ['label' => $isAdmin ? 'All applications' : 'My applications', 'url' => route('applications.index')],
        ['label' => Str::limit($application->reference, 13, '…')],
    ]">

    <x-slot:subtitle>{{ $application->reference }}</x-slot:subtitle>

    <x-slot:actions>
        @can('update', $application)
            <a href="{{ route('applications.edit', $application) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil" aria-hidden="true"></i> Edit
            </a>
        @endcan

        @can('submit', $application)
            <form method="POST" action="{{ route('applications.submit', $application) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-aidbridge btn-sm">
                    <i class="bi bi-send" aria-hidden="true"></i> Submit application
                </button>
            </form>
        @endcan

        @can('withdraw', $application)
            @unless ($application->isEditable())
                <form method="POST" action="{{ route('applications.withdraw', $application) }}"
                      onsubmit="return confirm('Withdraw this application? This cannot be undone.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-outline-danger btn-sm">Withdraw</button>
                </form>
            @endunless
        @endcan
    </x-slot:actions>
</x-page-header>

{{-- Status strip: where this application currently sits. --}}
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3 py-3">
        <div>
            <div class="stat-label">Current status</div>
            <x-status-badge :status="$status" />
        </div>

        <div class="vr d-none d-sm-block"></div>

        <div>
            <div class="stat-label">Submitted</div>
            <div class="fw-semibold small">
                {{ $application->submitted_at?->format('d M Y, H:i') ?? 'Not yet submitted' }}
            </div>
        </div>

        @if ($application->decided_at)
            <div class="vr d-none d-sm-block"></div>
            <div>
                <div class="stat-label">Decided</div>
                <div class="fw-semibold small">
                    {{ $application->decided_at->format('d M Y') }}
                    @if ($isAdmin && $application->decider)
                        <span class="text-muted fw-normal">by {{ $application->decider->name }}</span>
                    @endif
                </div>
            </div>
        @endif

        @if ($application->eligibility_score !== null)
            <div class="vr d-none d-sm-block"></div>
            <div>
                <div class="stat-label">Priority score</div>
                <div class="fw-bold">{{ $application->eligibility_score }} <span class="text-muted fw-normal small">/ 100</span></div>
            </div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">

        {{-- Household details --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-house-door" aria-hidden="true"></i> Household details</div>
            <div class="card-body">
                <dl class="row mb-0 detail-list">
                    @if ($isAdmin)
                        <dt class="col-sm-5">Applicant</dt>
                        <dd class="col-sm-7">
                            {{ $application->user->name }}
                            <div class="text-muted small">{{ $application->user->email }}</div>
                            {{-- Only the masked NRIC is ever rendered. --}}
                            <div class="text-muted small mono">NRIC {{ $application->user->masked_nric ?? 'n/a' }}</div>
                        </dd>
                    @endif

                    <dt class="col-sm-5">Gross household income</dt>
                    <dd class="col-sm-7">RM {{ number_format((float) $application->household_income, 2) }} <span class="text-muted">/ month</span></dd>

                    <dt class="col-sm-5">Dependents</dt>
                    <dd class="col-sm-7">{{ $application->dependents_count }}</dd>

                    <dt class="col-sm-5">State</dt>
                    <dd class="col-sm-7">{{ $application->state ?: '—' }}</dd>

                    <dt class="col-sm-5">Disaster affected</dt>
                    <dd class="col-sm-7">
                        @if ($application->is_disaster_victim)
                            <span class="badge text-bg-warning">
                                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Yes
                            </span>
                        @else
                            <span class="text-muted">No</span>
                        @endif
                    </dd>

                    @if ($application->notes)
                        <dt class="col-sm-5">Notes</dt>
                        <dd class="col-sm-7">{{ $application->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Documents --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-paperclip" aria-hidden="true"></i> Supporting documents</span>
                <span class="small text-muted fw-normal">
                    Required:
                    @foreach ($requiredDocuments as $doc)
                        <span class="badge badge-soft">{{ ucwords(str_replace('_', ' ', $doc)) }}</span>
                    @endforeach
                </span>
            </div>

            <ul class="list-group list-group-flush">
                @forelse ($application->documents as $document)
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <i class="bi bi-file-earmark-{{ str_contains($document->mime_type, 'pdf') ? 'pdf' : 'image' }}"
                               aria-hidden="true"></i>
                            <strong>{{ $document->type_label }}</strong>

                            @if ($document->isVerified())
                                <span class="badge text-bg-success ms-1">
                                    <i class="bi bi-check-lg" aria-hidden="true"></i> Verified
                                </span>
                            @elseif ($document->rejection_reason)
                                <span class="badge text-bg-danger ms-1">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i> Rejected
                                </span>
                            @else
                                <span class="badge text-bg-secondary ms-1">
                                    <i class="bi bi-hourglass-split" aria-hidden="true"></i> Pending check
                                </span>
                            @endif

                            <div class="text-muted small">
                                {{ $document->original_name }} &middot; {{ $document->human_size }}
                            </div>

                            @if ($document->rejection_reason)
                                <div class="text-danger small">{{ $document->rejection_reason }}</div>
                            @endif
                        </div>

                        <div class="d-flex gap-1">
                            {{-- Time-limited signed URL; the policy still runs on the request. --}}
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ URL::temporarySignedRoute('documents.download', now()->addMinutes(15), $document) }}">
                                <i class="bi bi-download" aria-hidden="true"></i>
                                <span class="visually-hidden">Download {{ $document->type_label }}</span>
                            </a>

                            @can('verify', $document)
                                <form method="POST" action="{{ route('documents.verify', $document) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="decision" value="verify">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Mark verified">
                                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                                        <span class="visually-hidden">Verify {{ $document->type_label }}</span>
                                    </button>
                                </form>
                            @endcan

                            @can('delete', $document)
                                <form method="POST" action="{{ route('documents.destroy', $document) }}"
                                      onsubmit="return confirm('Remove this document?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                        <span class="visually-hidden">Delete {{ $document->type_label }}</span>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </li>
                @empty
                    <li class="list-group-item">
                        <x-empty-state
                            icon="paperclip"
                            title="No documents uploaded yet"
                            message="Upload the required documents before submitting." />
                    </li>
                @endforelse
            </ul>

            @if ($application->isEditable() && auth()->id() === $application->user_id)
                <div class="card-body border-top">
                    {{-- enctype is required for file uploads. --}}
                    <form method="POST" action="{{ route('documents.store', $application) }}"
                          enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-5">
                            <label for="document_type" class="form-label small mb-1">Document type</label>
                            <select id="document_type" name="document_type" class="form-select form-select-sm" required>
                                <option value="nric">NRIC scan</option>
                                <option value="income_proof">Income proof</option>
                                <option value="household_proof">Household proof</option>
                                <option value="disability_cert">Disability certificate</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="file" class="form-label small mb-1">File</label>
                            <input type="file" id="file" name="file"
                                   class="form-control form-control-sm @error('file') is-invalid @enderror"
                                   accept=".png,.jpg,.jpeg,.pdf" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-aidbridge w-100">
                                <i class="bi bi-upload" aria-hidden="true"></i> Upload
                            </button>
                        </div>
                        <div class="col-12">
                            <div class="form-text">
                                PNG, JPG or PDF, up to 4 MB. Files are stored outside the web root and never served publicly.
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        {{-- Audit trail: admin only --}}
        @if ($isAdmin && $auditTrail->isNotEmpty())
            <div class="card">
                <div class="card-header"><i class="bi bi-shield-lock" aria-hidden="true"></i> Audit trail</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <caption class="visually-hidden">Recorded actions against this application</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width:150px">When</th>
                                <th scope="col">Action</th>
                                <th scope="col">Actor</th>
                                <th scope="col" style="width:100px">Trace</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($auditTrail as $log)
                            <tr>
                                <td class="text-muted small">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td><span class="mono">{{ $log->action }}</span></td>
                                <td class="small">{{ $log->user->name ?? 'System' }}</td>
                                <td class="small">
                                    {{-- Rows sharing a trace were written by one request. --}}
                                    @if ($log->correlation_id)
                                        <span class="badge badge-soft mono" title="{{ $log->correlation_id }}">
                                            {{ substr($log->correlation_id, 0, 8) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>

    <div class="col-lg-5">

        {{-- Eligibility assessment (Module 3 output) --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clipboard-data" aria-hidden="true"></i> Eligibility assessment</div>
            <div class="card-body">
                @if (! $breakdown)
                    <p class="text-muted mb-0">
                        Not yet assessed.
                        @if ($isAdmin) Run the assessment below to score this application. @endif
                    </p>
                @else
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <span class="stat-value {{ $breakdown['eligible'] ? 'text-success' : 'text-danger' }}">
                            {{ $breakdown['blended_score'] }}
                        </span>
                        <span class="text-muted">/ 100 priority score</span>
                    </div>

                    {{-- Visual read of the score against the recommendation threshold. --}}
                    <div class="progress mb-3" style="height:6px"
                         role="progressbar" aria-label="Priority score"
                         aria-valuenow="{{ $breakdown['blended_score'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $breakdown['eligible'] ? 'bg-success' : 'bg-danger' }}"
                             style="width: {{ $breakdown['blended_score'] }}%"></div>
                    </div>

                    <p class="mb-3 d-flex flex-wrap gap-1">
                        <span class="badge text-bg-{{ $breakdown['eligible'] ? 'success' : 'danger' }}">
                            {{ $breakdown['eligible'] ? 'Eligible' : 'Not eligible' }}
                        </span>
                        <span class="badge text-bg-info">
                            Recommendation: {{ ucwords(str_replace('_', ' ', $breakdown['recommendation'])) }}
                        </span>
                        @if ($breakdown['flagged_for_review'])
                            <span class="badge text-bg-warning">
                                <i class="bi bi-flag-fill" aria-hidden="true"></i> Flagged
                            </span>
                        @endif
                    </p>

                    {{-- One panel per Strategy that applied, with its reasons. --}}
                    <div class="accordion accordion-flush" id="strategyAccordion">
                        @foreach ($breakdown['strategies'] as $i => $strategy)
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed py-2" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#strategy{{ $i }}"
                                            aria-expanded="false" aria-controls="strategy{{ $i }}">
                                        <span class="me-2">{{ ucwords(str_replace('_', ' ', $strategy['strategy'])) }}</span>
                                        <span class="badge text-bg-{{ $strategy['eligible'] ? 'success' : 'danger' }}">
                                            {{ $strategy['score'] }}
                                        </span>
                                    </button>
                                </h3>
                                <div id="strategy{{ $i }}" class="accordion-collapse collapse"
                                     data-bs-parent="#strategyAccordion">
                                    <div class="accordion-body small">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($strategy['reasons'] as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-muted small mt-2 mb-0">
                        Assessed {{ \Carbon\Carbon::parse($breakdown['assessed_at'])->format('d M Y, H:i') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- External registry verification --}}
        @if ($isAdmin && $verification)
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-patch-check" aria-hidden="true"></i> Agency registry check</div>
                <div class="card-body">
                    <p class="mb-1">
                        <span class="badge text-bg-{{ match($verification['status'] ?? '') {
                            'matched' => 'success', 'discrepancy' => 'warning', default => 'secondary'
                        } }}">
                            {{ ucfirst($verification['status'] ?? 'unknown') }}
                        </span>
                    </p>
                    <p class="small text-muted mb-0">{{ $verification['message'] ?? '' }}</p>

                    @if (isset($verification['registry_income']))
                        <dl class="row small mt-2 mb-0 detail-list">
                            <dt class="col-7">Declared</dt>
                            <dd class="col-5">RM {{ number_format((float) $verification['declared_income'], 2) }}</dd>
                            <dt class="col-7">Registry</dt>
                            <dd class="col-5">RM {{ number_format((float) $verification['registry_income'], 2) }}</dd>
                            <dt class="col-7">Discrepancy</dt>
                            <dd class="col-5">{{ $verification['discrepancy_percent'] }}%</dd>
                        </dl>
                    @endif
                </div>
            </div>
        @endif

        {{-- Admin actions --}}
        @if ($isAdmin && ! $status->isFinal() && $status->value !== 'draft')
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-check" aria-hidden="true"></i> Reviewer actions</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('eligibility.assess', $application) }}" class="mb-3">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-calculator" aria-hidden="true"></i>
                            {{ $breakdown ? 'Re-run assessment' : 'Run eligibility assessment' }}
                        </button>
                        <div class="form-text">
                            Runs all applicable strategies and queries the agency registry.
                        </div>
                    </form>

                    <hr>

                    <form method="POST" action="{{ route('eligibility.decide', $application) }}">
                        @csrf
                        <label for="note" class="form-label small">Decision note <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea id="note" name="note" rows="2" class="form-control form-control-sm mb-2"
                                  maxlength="1000">{{ old('note') }}</textarea>
                        <div class="d-flex gap-2">
                            <button type="submit" name="decision" value="approve" class="btn btn-success flex-grow-1">
                                <i class="bi bi-check-lg" aria-hidden="true"></i> Approve
                            </button>
                            <button type="submit" name="decision" value="reject" class="btn btn-outline-danger flex-grow-1">
                                <i class="bi bi-x-lg" aria-hidden="true"></i> Reject
                            </button>
                        </div>
                        <div class="form-text">
                            Approving also raises the payout automatically.
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Disbursement --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-cash-coin" aria-hidden="true"></i> Payment</div>
            <div class="card-body">
                @if ($application->disbursement)
                    @php $d = $application->disbursement; @endphp
                    <div class="stat-value mb-1">RM {{ number_format((float) $d->amount, 2) }}</div>
                    <p class="mb-2">
                        <x-status-badge :status="$d->status" />
                        <span class="mono small text-muted ms-1">{{ $d->reference_code }}</span>
                    </p>
                    <a href="{{ route('disbursements.show', $d) }}" class="small text-decoration-none">
                        View payment record <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @elseif ($status->value === 'approved' && $isAdmin)
                    <p class="text-muted small">No payment scheduled yet.</p>
                    <form method="POST" action="{{ route('disbursements.store', $application) }}">
                        @csrf
                        <button type="submit" class="btn btn-aidbridge w-100">Schedule disbursement</button>
                    </form>
                @else
                    <p class="text-muted mb-0 small">
                        A payment record appears here once the application is approved.
                    </p>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
