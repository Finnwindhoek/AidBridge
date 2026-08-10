@extends('layouts.app')
@section('title', 'Aid Programmes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Aid Programmes</h1>
        <p class="text-muted small mb-0">
            {{ auth()->user()->isAdmin()
                ? 'Create and manage targeted welfare programmes.'
                : 'Programmes currently open for application.' }}
        </p>
    </div>
    @can('create', App\Models\AidProgram::class)
        <a href="{{ route('aid-programs.create') }}" class="btn btn-aidbridge">
            <i class="bi bi-plus-lg"></i> New programme
        </a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search title</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if (auth()->user()->isAdmin())
                <div class="col-md-3">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary flex-grow-1">Filter</button>
                <a href="{{ route('aid-programs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Programme</th>
                    <th>Type</th>
                    <th class="text-end">Payout</th>
                    <th style="min-width:180px">Budget</th>
                    <th class="text-center">Applications</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($programs as $program)
                <tr>
                    <td>
                        <a href="{{ route('aid-programs.show', $program) }}" class="fw-semibold text-decoration-none">
                            {{ $program->title }}
                        </a>
                        @if ($program->closes_at)
                            <div class="text-muted small">Closes {{ $program->closes_at->format('d M Y') }}</div>
                        @endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ $program->type->label() }}</span></td>
                    <td class="text-end">RM {{ number_format((float) $program->payout_amount, 2) }}</td>
                    <td>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-aidbridge" style="width: {{ $program->budget_used_percent }}%"></div>
                        </div>
                        <div class="small text-muted mt-1">
                            RM {{ number_format((float) $program->budget_remaining, 0) }} of
                            RM {{ number_format((float) $program->budget_allocated, 0) }} left
                        </div>
                    </td>
                    <td class="text-center">{{ $program->applications_count }}</td>
                    <td><span class="badge bg-{{ $program->status->colour() }}">{{ $program->status->label() }}</span></td>
                    <td class="text-end">
                        @can('update', $program)
                            <a href="{{ route('aid-programs.edit', $program) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No programmes found.
                        @can('create', App\Models\AidProgram::class)
                            <a href="{{ route('aid-programs.create') }}">Create the first one.</a>
                        @endcan
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $programs->links() }}</div>
@endsection
