@extends('layouts.app')
@section('title', 'Application ' . Str::limit($application->reference, 8, ''))

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $breakdown = $application->eligibility_breakdown;
    $verification = $application->agency_verification;
@endphp

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1">{{ $application->aidProgram->title }}</h1>
        <span class="mono text-muted">{{ $application->reference }}</span>
        <span class="badge bg-{{ $application->status->colour() }} ms-2">{{ $application->status->label() }}</span>
    </div>
    <div class="d-flex gap-2">
        @can('update', $application)
            <a href="{{ route('applications.edit', $application) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil"></i> Edit
            </a>
        @endcan
        @can('submit', $application)
            <form method="POST" action="{{ route('applications.submit', $application) }}">
                @csrf @method('PATCH')
                <button class="btn btn-aidbridge btn-sm">Submit application</button>
            </form>
        @endcan
        @can('withdraw', $application)
            @unless ($application->isEditable())
                <form method="POST" action="{{ route('applications.withdraw', $application) }}"
                      onsubmit="return confirm('Withdraw this application?')">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-danger btn-sm">Withdraw</button>
                </form>
            @endunless
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">

        {{-- Household details --}}
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Household details</div>
            <div class="card-body">
                <dl class="row mb-0">
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
                    <dd class="col-sm-7">RM {{ number_format((float) $application->household_income, 2) }} / month</dd>

                    <dt class="col-sm-5">Dependents</dt>
                    <dd class="col-sm-7">{{ $application->dependents_count }}</dd>

                    <dt class="col-sm-5">State</dt>
                    <dd class="col-sm-7">{{ $application->state ?? '—' }}</dd>

                    <dt class="col-sm-5">Disaster affected</dt>
                    <dd class="col-sm-7">
                        @if ($application->is_disaster_victim)
                            <span class="badge bg-warning text-dark">Yes</span>
                        @else
                            No
                        @endif
                    </dd>

                    <dt class="col-sm-5">Submitted</dt>
                    <dd class="col-sm-7">{{ $application->submitted_at?->format('d M Y, H:i') ?? 'Not yet submitted' }}</dd>

                    @if ($application->decided_at)
                        <dt class="col-sm-5">Decision</dt>
                        <dd class="col-sm-7">
                            {{ $application->status->label() }} on {{ $application->decided_at->format('d M Y') }}
                            @if ($isAdmin && $application->decider)
                                <div class="text-muted small">by {{ $application->decider->name }}</div>
                            @endif
                        </dd>
                    @endif

                    @if ($application->notes)
                        <dt class="col-sm-5">Notes</dt>
                        <dd class="col-sm-7">{{ $application->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Documents --}}
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Supporting documents</span>
                <span class="small text-muted">
                    Required:
                    @foreach ($requiredDocuments as $doc)
                        <span class="badge bg-secondary-subtle text-dark">
                            {{ ucwords(str_replace('_', ' ', $doc)) }}
                        </span>
                    @endforeach
                </span>
            </div>

            <ul class="list-group list-group-flush">
                @forelse ($application->documents as $document)
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <i class="bi bi-file-earmark-{{ str_contains($document->mime_type, 'pdf') ? 'pdf' : 'image' }}"></i>
                            <strong>{{ $document->type_label }}</strong>
                            <div class="text-muted small">
                                {{ $document->original_name }} · {{ $document->human_size }}
                                @if ($document->isVerified())
                                    <span class="badge bg-success ms-1">Verified</span>
                                @elseif ($document->rejection_reason)
                                    <span class="badge bg-danger ms-1">Rejected</span>
                                @else
                                    <span class="badge bg-secondary ms-1">Pending check</span>
                                @endif
                            </div>
                            @if ($document->rejection_reason)
                                <div class="text-danger small">{{ $document->rejection_reason }}</div>
                            @endif
                        </div>

                        <div class="d-flex gap-1">
                            {{-- Time-limited signed URL; the policy still runs on the request. --}}
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ URL::temporarySignedRoute('documents.download', now()->addMinutes(15), $document) }}">
                                <i class="bi bi-download"></i>
                            </a>

                            @can('verify', $document)
                                <form method="POST" action="{{ route('documents.verify', $document) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="decision" value="verify">
                                    <button class="btn btn-sm btn-outline-success" title="Mark verified">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                            @endcan

                            @can('delete', $document)
                                <form method="POST" action="{{ route('documents.destroy', $document) }}"
                                      onsubmit="return confirm('Remove this document?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted text-center py-3">No documents uploaded yet.</li>
                @endforelse
            </ul>

            @if ($application->isEditable() && auth()->id() === $application->user_id)
                <div class="card-body border-top">
                    {{-- enctype is required for file uploads. --}}
                    <form method="POST" action="{{ route('documents.store', $application) }}"
                          enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Document type</label>
                            <select name="document_type" class="form-select form-select-sm" required>
                                <option value="nric">NRIC scan</option>
                                <option value="income_proof">Income proof</option>
                                <option value="household_proof">Household proof</option>
                                <option value="disability_cert">Disability certificate</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">File</label>
                            <input type="file" name="file" class="form-control form-control-sm"
                                   accept=".png,.jpg,.jpeg,.pdf" required>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-aidbridge w-100">Upload</button>
                        </div>
                        <div class="col-12">
                            <div class="form-text">PNG, JPG or PDF, up to 4 MB. Files are stored encrypted at rest and never served publicly.</div>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        {{-- Audit trail: admin only --}}
        @if ($isAdmin && $auditTrail->isNotEmpty())
            <div class="card">
                <div class="card-header bg-white fw-semibold">Audit trail</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach ($auditTrail as $log)
                            <tr>
                                <td class="text-muted small" style="width:150px">
                                    {{ $log->created_at->format('d M Y H:i') }}
                                </td>
                                <td><span class="mono">{{ $log->action }}</span></td>
                                <td class="small">{{ $log->user->name ?? 'System' }}</td>
                                <td class="small text-muted mono">{{ $log->ip_address }}</td>
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
            <div class="card-header bg-white fw-semibold">Eligibility assessment</div>
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

                    <p class="mb-2">
                        <span class="badge bg-{{ $breakdown['eligible'] ? 'success' : 'danger' }}">
                            {{ $breakdown['eligible'] ? 'Eligible' : 'Not eligible' }}
                        </span>
                        <span class="badge bg-info text-dark">
                            Recommendation: {{ ucwords(str_replace('_', ' ', $breakdown['recommendation'])) }}
                        </span>
                        @if ($breakdown['flagged_for_review'])
                            <span class="badge bg-warning text-dark">Flagged</span>
                        @endif
                    </p>

                    <div class="accordion accordion-flush" id="strategyAccordion">
                        @foreach ($breakdown['strategies'] as $i => $strategy)
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#strategy{{ $i }}">
                                        <span class="me-2">{{ ucwords(str_replace('_', ' ', $strategy['strategy'])) }}</span>
                                        <span class="badge bg-{{ $strategy['eligible'] ? 'success' : 'danger' }}">
                                            {{ $strategy['score'] }}
                                        </span>
                                    </button>
                                </h2>
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
                <div class="card-header bg-white fw-semibold">Agency registry check</div>
                <div class="card-body">
                    <p class="mb-1">
                        <span class="badge bg-{{ match($verification['status'] ?? '') {
                            'matched' => 'success', 'discrepancy' => 'warning', default => 'secondary'
                        } }}">
                            {{ ucfirst($verification['status'] ?? 'unknown') }}
                        </span>
                    </p>
                    <p class="small text-muted mb-0">{{ $verification['message'] ?? '' }}</p>
                    @if (isset($verification['registry_income']))
                        <dl class="row small mt-2 mb-0">
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
        @if ($isAdmin && ! $application->status->isFinal() && $application->status->value !== 'draft')
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Reviewer actions</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('eligibility.assess', $application) }}" class="mb-3">
                        @csrf
                        <button class="btn btn-outline-primary w-100">
                            <i class="bi bi-calculator"></i>
                            {{ $breakdown ? 'Re-run assessment' : 'Run eligibility assessment' }}
                        </button>
                        <div class="form-text">
                            Runs all applicable strategies and queries the agency registry.
                        </div>
                    </form>

                    <form method="POST" action="{{ route('eligibility.decide', $application) }}">
                        @csrf
                        <label for="note" class="form-label small">Decision note (optional)</label>
                        <textarea id="note" name="note" rows="2" class="form-control form-control-sm mb-2"
                                  maxlength="1000">{{ old('note') }}</textarea>
                        <div class="d-flex gap-2">
                            <button name="decision" value="approve" class="btn btn-success flex-grow-1">Approve</button>
                            <button name="decision" value="reject" class="btn btn-outline-danger flex-grow-1">Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Disbursement --}}
        <div class="card">
            <div class="card-header bg-white fw-semibold">Payment</div>
            <div class="card-body">
                @if ($application->disbursement)
                    @php $d = $application->disbursement; @endphp
                    <div class="stat-value mb-1">RM {{ number_format((float) $d->amount, 2) }}</div>
                    <p class="mb-2">
                        <span class="badge bg-{{ $d->status->colour() }}">{{ $d->status->label() }}</span>
                        <span class="mono small text-muted ms-1">{{ $d->reference_code }}</span>
                    </p>
                    <a href="{{ route('disbursements.show', $d) }}" class="small text-decoration-none">
                        View payment record →
                    </a>
                @elseif ($application->status->value === 'approved' && $isAdmin)
                    <p class="text-muted small">No payment scheduled yet.</p>
                    <form method="POST" action="{{ route('disbursements.store', $application) }}">
                        @csrf
                        <button class="btn btn-aidbridge w-100">Schedule disbursement</button>
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
