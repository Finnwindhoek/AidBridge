@extends('layouts.app')
@section('title', 'Disbursement ' . $disbursement->reference_code)

@section('content')
@php
    use App\Enums\DisbursementStatus;

    $isAdmin = auth()->user()->isAdmin();
    $hasFailed = $disbursement->status === DisbursementStatus::Failed;

    // The happy path, in order. Failure is handled separately below because it
    // can branch off from more than one point in this sequence.
    $lifecycle = [
        DisbursementStatus::Pending,
        DisbursementStatus::Approved,
        DisbursementStatus::Disbursed,
        DisbursementStatus::Reconciled,
    ];
    $currentIndex = array_search($disbursement->status, $lifecycle, true);
@endphp

<x-page-header
    title="Disbursement"
    :breadcrumbs="$isAdmin
        ? [['label' => 'Disbursements', 'url' => route('disbursements.index')], ['label' => $disbursement->reference_code]]
        : null">

    <x-slot:subtitle>{{ $disbursement->reference_code }}</x-slot:subtitle>

    <x-slot:actions>
        <a href="{{ route('applications.show', $disbursement->application) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i> View application
        </a>
    </x-slot:actions>
</x-page-header>

{{-- Lifecycle progress, driven by the State pattern --}}
@if (! $hasFailed)
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                @foreach ($lifecycle as $step)
                    @php $done = $currentIndex !== false && $loop->index <= $currentIndex; @endphp
                    <div class="text-center flex-fill">
                        <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center
                                    {{ $done ? 'bg-aidbridge text-white' : 'bg-light text-muted border' }}"
                             style="width:34px;height:34px">
                            @if ($done)
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </div>
                        <div class="small {{ $done ? 'fw-semibold' : 'text-muted' }}">{{ $step->label() }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="alert alert-danger d-flex gap-2">
        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>Payment failed.</strong>
            {{ $disbursement->failure_reason }}
            Any committed budget has been returned to the programme.
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-journal-text" aria-hidden="true"></i> Ledger record</div>
            <div class="card-body">
                <div class="stat-label">Payout amount</div>
                <div class="stat-value text-aidbridge mb-3">RM {{ number_format((float) $disbursement->amount, 2) }}</div>

                <dl class="row mb-0 detail-list">
                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7"><x-status-badge :status="$disbursement->status" /></dd>

                    <dt class="col-sm-5">Beneficiary</dt>
                    <dd class="col-sm-7">{{ $disbursement->application->user->name }}</dd>

                    <dt class="col-sm-5">Programme</dt>
                    <dd class="col-sm-7">{{ $disbursement->application->aidProgram->title }}</dd>

                    <dt class="col-sm-5">Application</dt>
                    <dd class="col-sm-7 mono small">{{ $disbursement->application->reference }}</dd>

                    <dt class="col-sm-5">Payment channel</dt>
                    <dd class="col-sm-7">{{ ucwords(str_replace('_', ' ', $disbursement->payment_channel)) }}</dd>

                    <dt class="col-sm-5">Created</dt>
                    <dd class="col-sm-7">{{ $disbursement->created_at->format('d M Y, H:i') }}</dd>

                    @if ($disbursement->approved_at)
                        <dt class="col-sm-5">Approved</dt>
                        <dd class="col-sm-7">
                            {{ $disbursement->approved_at->format('d M Y, H:i') }}
                            @if ($isAdmin && $disbursement->approver)
                                <div class="text-muted small">by {{ $disbursement->approver->name }}</div>
                            @endif
                        </dd>
                    @endif

                    @if ($disbursement->disbursed_at)
                        <dt class="col-sm-5">Disbursed</dt>
                        <dd class="col-sm-7">{{ $disbursement->disbursed_at->format('d M Y, H:i') }}</dd>
                    @endif

                    @if ($disbursement->bank_reference)
                        <dt class="col-sm-5">Bank reference</dt>
                        <dd class="col-sm-7 mono small">{{ $disbursement->bank_reference }}</dd>
                    @endif

                    @if ($disbursement->reconciled_at)
                        <dt class="col-sm-5">Reconciled</dt>
                        <dd class="col-sm-7">{{ $disbursement->reconciled_at->format('d M Y, H:i') }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @if ($isAdmin)
            <div class="card">
                <div class="card-header"><i class="bi bi-sliders" aria-hidden="true"></i> Lifecycle actions</div>
                <div class="card-body">
                    @if (! $state->isActionable())
                        <p class="text-muted mb-0">
                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            This disbursement is <strong>{{ $state->label() }}</strong> and is final.
                            No further action is possible.
                        </p>
                    @else
                        <p class="small text-muted">
                            Allowed from {{ $state->label() }}:
                            @foreach ($state->allowedTransitions() as $next)
                                <span class="badge badge-soft">{{ $next->label() }}</span>
                            @endforeach
                        </p>

                        @if ($state->canTransitionTo(DisbursementStatus::Approved))
                            <form method="POST" action="{{ route('disbursements.approve', $disbursement) }}" class="mb-3">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                    Approve &amp; commit RM {{ number_format((float) $disbursement->amount, 2) }}
                                </button>
                                <div class="form-text">Debits the programme budget in the same transaction.</div>
                            </form>
                        @endif

                        @if ($state->canTransitionTo(DisbursementStatus::Disbursed))
                            <form method="POST" action="{{ route('disbursements.disburse', $disbursement) }}" class="mb-3">
                                @csrf @method('PATCH')
                                <label for="bank_reference" class="form-label small mb-1">
                                    Bank reference <span class="text-muted fw-normal">(optional)</span>
                                </label>
                                <input type="text" id="bank_reference" name="bank_reference"
                                       class="form-control form-control-sm mb-2" maxlength="255">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-send-fill" aria-hidden="true"></i> Mark as paid
                                </button>
                            </form>
                        @endif

                        @if ($state->canTransitionTo(DisbursementStatus::Reconciled))
                            <form method="POST" action="{{ route('disbursements.reconcile', $disbursement) }}" class="mb-3">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-outline-success w-100">
                                    <i class="bi bi-shield-check" aria-hidden="true"></i> Reconcile against statement
                                </button>
                            </form>
                        @endif

                        @if ($state->canTransitionTo(DisbursementStatus::Failed))
                            <hr>
                            <form method="POST" action="{{ route('disbursements.fail', $disbursement) }}"
                                  onsubmit="return confirm('Mark this payment as failed? Committed budget will be released.')">
                                @csrf @method('PATCH')
                                <label for="failure_reason" class="form-label small mb-1">Failure reason</label>
                                <input type="text" id="failure_reason" name="failure_reason"
                                       class="form-control form-control-sm mb-2"
                                       required maxlength="255" placeholder="e.g. Invalid bank account">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">Mark as failed</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header"><i class="bi bi-info-circle" aria-hidden="true"></i> Payment status</div>
                <div class="card-body">
                    <p class="mb-0">
                        @switch($disbursement->status)
                            @case(DisbursementStatus::Pending) Your payment is awaiting final approval. @break
                            @case(DisbursementStatus::Approved) Your payment has been approved and is queued with the bank. @break
                            @case(DisbursementStatus::Disbursed) Your payment has been sent. Allow 1–3 working days to clear. @break
                            @case(DisbursementStatus::Reconciled) Your payment is complete and confirmed. @break
                            @case(DisbursementStatus::Failed) This payment did not go through. Please contact support. @break
                        @endswitch
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
