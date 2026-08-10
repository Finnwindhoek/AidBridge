@extends('layouts.app')
@section('title', 'Disbursements')

@section('content')
<h1 class="h4 mb-1">Disbursements</h1>
<p class="text-muted small mb-3">
    The financial ledger. Every state change is atomic and audit-logged.
</p>

<div class="row g-2 mb-3">
    @foreach ($statusOptions as $status)
        @php $row = $totals[$status->value] ?? null; @endphp
        <div class="col-6 col-lg">
            <div class="card stat-card h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">{{ $status->label() }}</div>
                    <div class="stat-value text-{{ $status->colour() }}">{{ $row->count ?? 0 }}</div>
                    <div class="small text-muted">RM {{ number_format((float) ($row->total ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Programme</label>
                <select name="programme" class="form-select form-select-sm">
                    <option value="">All programmes</option>
                    @foreach ($programmes as $programme)
                        <option value="{{ $programme->slug }}" @selected(request('programme') === $programme->slug)>
                            {{ $programme->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Reference</label>
                <input type="text" name="reference" value="{{ request('reference') }}"
                       class="form-control form-control-sm" placeholder="AB-2024…">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary flex-grow-1">Filter</button>
                <a href="{{ route('disbursements.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    <th>Beneficiary</th>
                    <th>Programme</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Next step</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($disbursements as $disbursement)
                @php $state = $disbursement->state(); @endphp
                <tr>
                    <td>
                        <a href="{{ route('disbursements.show', $disbursement) }}" class="mono text-decoration-none">
                            {{ $disbursement->reference_code }}
                        </a>
                        <div class="text-muted small">{{ $disbursement->created_at->format('d M Y') }}</div>
                    </td>
                    <td>{{ $disbursement->application->user->name }}</td>
                    <td>{{ $disbursement->application->aidProgram->title }}</td>
                    <td class="text-end fw-semibold">RM {{ number_format((float) $disbursement->amount, 2) }}</td>
                    <td><span class="badge bg-{{ $disbursement->status->colour() }}">{{ $disbursement->status->label() }}</span></td>
                    <td class="small text-muted">
                        {{-- Allowed transitions come straight from the State object. --}}
                        @forelse ($state->allowedTransitions() as $next)
                            <span class="badge bg-light text-dark">{{ $next->label() }}</span>
                        @empty
                            <span class="text-muted">Closed</span>
                        @endforelse
                    </td>
                    <td class="text-end">
                        <a href="{{ route('disbursements.show', $disbursement) }}" class="btn btn-sm btn-outline-secondary">
                            Manage
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No disbursements match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $disbursements->links() }}</div>
@endsection
