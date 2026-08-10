@extends('layouts.app')
@section('title', 'Audit Trail')

@section('content')
<h1 class="h4 mb-1">Audit trail</h1>
<p class="text-muted small mb-3">
    Immutable record of every action. Written automatically by
    <span class="mono">ApplicationObserver</span> and <span class="mono">AuditLogger</span>;
    sensitive values are redacted before storage.
</p>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1">Actor email contains</label>
                <input type="text" name="user" value="{{ request('user') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-secondary flex-grow-1">Filter</button>
                <a href="{{ route('reports.audit') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:160px">When</th>
                    <th>Action</th>
                    <th>Actor</th>
                    <th>Subject</th>
                    <th style="width:120px">IP</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="small text-muted">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                    <td><span class="mono">{{ $log->action }}</span></td>
                    <td class="small">
                        {{ $log->user->name ?? 'System' }}
                        @if ($log->user)
                            <div class="text-muted">{{ $log->user->email }}</div>
                        @endif
                    </td>
                    <td class="small">
                        {{ $log->subject_label }}
                        @if ($log->auditable_id)
                            <span class="text-muted">#{{ $log->auditable_id }}</span>
                        @endif
                    </td>
                    <td class="small mono text-muted">{{ $log->ip_address }}</td>
                    <td class="small">
                        @if ($log->payload)
                            <details>
                                <summary class="text-muted" style="cursor:pointer">View payload</summary>
                                {{-- Escaped output: a payload value can never inject markup. --}}
                                <pre class="mb-0 mt-1 small bg-light p-2 rounded">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No audit entries match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $logs->links() }}</div>
@endsection
