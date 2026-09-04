{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Module 5 — Reporting & Monitoring
    Author: Ng Yu Xun
--}}
@extends('layouts.app')
@section('title', 'Audit Trail')

@section('content')
<x-page-header
    title="Audit trail"
    subtitle="Immutable record of every action, written automatically by ApplicationObserver and AuditLogger. Sensitive values are redacted before storage, and rows sharing a trace badge were written by the same request — one action." />

<x-filter-card
    :action="route('reports.audit')"
    :count="$logs->total().' '.Str::plural('entry', $logs->total()).' recorded'">

    <div class="col-md-4">
        <label for="filter-action" class="form-label small mb-1">Action</label>
        <select id="filter-action" name="action" class="form-select form-select-sm">
            <option value="">All actions</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-5">
        <label for="filter-user" class="form-label small mb-1">Actor email contains</label>
        <input type="search" id="filter-user" name="user" value="{{ request('user') }}"
               class="form-control form-control-sm">
    </div>
</x-filter-card>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width:160px">When</th>
                    <th scope="col">Action</th>
                    <th scope="col">Actor</th>
                    <th scope="col">Subject</th>
                    <th scope="col" style="width:110px">Trace</th>
                    <th scope="col" style="width:120px">IP</th>
                    <th scope="col">Details</th>
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
                    <td class="small">
                        {{-- First 8 chars are enough to eyeball which rows belong together. --}}
                        @if ($log->correlation_id)
                            <span class="badge badge-soft mono"
                                  title="{{ $log->correlation_id }}">{{ substr($log->correlation_id, 0, 8) }}</span>
                        @else
                            <span class="text-muted">—</span>
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
                <tr>
                    <td colspan="7">
                        <x-empty-state
                            icon="shield-lock"
                            title="No audit entries found"
                            message="No recorded actions match these filters." />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($logs->hasPages())
    <div class="mt-3">{{ $logs->withQueryString()->links() }}</div>
@endif
@endsection
