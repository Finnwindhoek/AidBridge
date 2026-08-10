@extends('layouts.app')
@section('title', 'Disbursement ' . $disbursement->reference_code)

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $lifecycle = ['pending' => 'Pending', 'approved' => 'Approved', 'disbursed' => 'Disbursed', 'reconciled' => 'Reconciled'];
    $currentIndex = array_search($disbursement->status->value, array_keys($lifecycle), true);
@endphp

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1">Disbursement</h1>
        <span class="mono text-muted">{{ $disbursement->reference_code }}</span>
        <span class="badge bg-{{ $disbursement->status->colour() }} ms-2">{{ $disbursement->status->label() }}</span>
    </div>
    <a href="{{ route('applications.show', $disbursement->application) }}" class="btn btn-outline-secondary btn-sm">
        View application
    </a>
</div>

{{-- Lifecycle progress, driven by the State pattern --}}
@if ($disbursement->status->value !== 'failed')
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between position-relative">
                @foreach ($lifecycle as $key => $label)
                    @php $done = $currentIndex !== false && $loop->index <= $currentIndex; @endphp
                    <div class="text-center flex-fill">
                        <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center
                                    {{ $done ? 'bg-aidbridge text-white' : 'bg-light text-muted border' }}"
                             style="width:34px;height:34px">
                            @if ($done)<i class="bi bi-check-lg"></i>@else {{ $loop->iteration }} @endif
                        </div>
                        <div class="small {{ $done ? 'fw-semibold' : 'text-muted' }}">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="alert alert-danger">
        <strong>Payment failed.</strong>
        {{ $disbursement->failure_reason }}
        Any committed budget has been returned to the programme.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Ledger record</div>
            <div class="card-body">
                <div class="stat-value text-aidbridge mb-3">RM {{ number_format((float) $disbursement->amount, 2) }}</div>
                <dl class="row mb-0">
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
                <div class="card-header bg-white fw-semibold">Lifecycle actions</div>
                <div class="card-body">
                    @if (! $state->isActionable())
                        <p class="text-muted mb-0">
                            This disbursement is <strong>{{ $state->label() }}</strong> and is final.
                            No further action is possible.
                        </p>
                    @else
                        <p class="small text-muted">
                            Allowed from {{ $state->label() }}:
                            @foreach ($state->allowedTransitions() as $next)
                                <span class="badge bg-light text-dark">{{ $next->label() }}</span>
                            @endforeach
                        </p>

                        @if ($state->canTransitionTo(App\Enums\DisbursementStatus::Approved))
                            <form method="POST" action="{{ route('disbursements.approve', $disbursement) }}" class="mb-2">
                                @csrf @method('PATCH')
                                <button class="btn btn-success w-100">
                                    Approve &amp; commit RM {{ number_format((float) $disbursement->amount, 2) }}
                                </button>
                                <div class="form-text">Debits the programme budget in the same transaction.</div>
                            </form>
                        @endif

                        @if ($state->canTransitionTo(App\Enums\DisbursementStatus::Disbursed))
                            <form method="POST" action="{{ route('disbursements.disburse', $disbursement) }}" class="mb-2">
                                @csrf @method('PATCH')
                                <label class="form-label small mb-1">Bank reference (optional)</label>
                                <input type="text" name="bank_reference" class="form-control form-control-sm mb-2"
                                       maxlength="255">
                                <button class="btn btn-primary w-100">Mark as paid</button>
                            </form>
                        @endif

                        @if ($state->canTransitionTo(App\Enums\DisbursementStatus::Reconciled))
                            <form method="POST" action="{{ route('disbursements.reconcile', $disbursement) }}" class="mb-2">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-success w-100">Reconcile against statement</button>
                            </form>
                        @endif

                        @if ($state->canTransitionTo(App\Enums\DisbursementStatus::Failed))
                            <hr>
                            <form method="POST" action="{{ route('disbursements.fail', $disbursement) }}"
                                  onsubmit="return confirm('Mark this payment as failed? Committed budget will be released.')">
                                @csrf @method('PATCH')
                                <label class="form-label small mb-1">Failure reason</label>
                                <input type="text" name="failure_reason" class="form-control form-control-sm mb-2"
                                       required maxlength="255" placeholder="e.g. Invalid bank account">
                                <button class="btn btn-outline-danger w-100 btn-sm">Mark as failed</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-2">Payment status</h2>
                    <p class="mb-0">
                        @switch($disbursement->status->value)
                            @case('pending') Your payment is awaiting final approval. @break
                            @case('approved') Your payment has been approved and is queued with the bank. @break
                            @case('disbursed') Your payment has been sent. Allow 1–3 working days to clear. @break
                            @case('reconciled') Your payment is complete and confirmed. @break
                            @case('failed') This payment did not go through. Please contact support. @break
                        @endswitch
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
