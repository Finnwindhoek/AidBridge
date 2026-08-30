@extends('layouts.app')
@section('title', 'Aid Programmes')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

<x-page-header
    title="Aid programmes"
    :subtitle="$isAdmin
        ? 'Create and manage targeted welfare programmes.'
        : 'Programmes currently open for application.'">
    @can('create', App\Models\AidProgram::class)
        <x-slot:actions>
            <a href="{{ route('aid-programs.create') }}" class="btn btn-aidbridge">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> New programme
            </a>
        </x-slot:actions>
    @endcan
</x-page-header>

<x-filter-card
    :action="route('aid-programs.index')"
    :count="$programs->total().' '.Str::plural('programme', $programs->total()).' found'">

    <div class="col-md-4">
        <label for="filter-q" class="form-label small mb-1">Search title</label>
        <input type="search" id="filter-q" name="q" value="{{ request('q') }}"
               class="form-control form-control-sm" placeholder="e.g. Food Subsidy">
    </div>

    <div class="col-md-3">
        <label for="filter-type" class="form-label small mb-1">Type</label>
        <select id="filter-type" name="type" class="form-select form-select-sm">
            <option value="">All types</option>
            @foreach ($typeOptions as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if ($isAdmin)
        <div class="col-md-3">
            <label for="filter-status" class="form-label small mb-1">Status</label>
            <select id="filter-status" name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif
</x-filter-card>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <caption class="visually-hidden">Aid programmes with budget utilisation and status</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Programme</th>
                    <th scope="col">Type</th>
                    <th scope="col" class="text-end">Payout</th>
                    <th scope="col" style="min-width:190px">Budget</th>
                    <th scope="col" class="text-center">Applications</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($programs as $program)
                @php $used = (float) $program->budget_used_percent; @endphp
                <tr>
                    <td>
                        <a href="{{ route('aid-programs.show', $program) }}" class="fw-semibold text-decoration-none">
                            {{ $program->title }}
                        </a>
                        @if ($program->closes_at)
                            <div class="text-muted small">
                                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                Closes {{ $program->closes_at->format('d M Y') }}
                            </div>
                        @endif
                    </td>

                    <td>
                        <span class="badge badge-soft">
                            <i class="bi bi-{{ $program->type->icon() }}" aria-hidden="true"></i>
                            {{ $program->type->label() }}
                        </span>
                    </td>

                    <td class="text-end">RM {{ number_format((float) $program->payout_amount, 2) }}</td>

                    <td>
                        {{-- Bar turns amber then red as the programme runs its budget down. --}}
                        @php
                            $barColour = $used >= 90 ? 'bg-danger' : ($used >= 70 ? 'bg-warning' : 'bg-aidbridge');
                        @endphp
                        <div class="progress" style="height:6px"
                             role="progressbar" aria-label="Budget used"
                             aria-valuenow="{{ round($used) }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar {{ $barColour }}" style="width: {{ $used }}%"></div>
                        </div>
                        <div class="small text-muted mt-1">
                            RM {{ number_format((float) $program->budget_remaining, 0) }} of
                            RM {{ number_format((float) $program->budget_allocated, 0) }} left
                        </div>
                    </td>

                    <td class="text-center">{{ $program->applications_count }}</td>

                    <td><x-status-badge :status="$program->status" /></td>

                    <td class="text-end">
                        @can('update', $program)
                            <a href="{{ route('aid-programs.edit', $program) }}"
                               class="btn btn-sm btn-outline-secondary" aria-label="Edit {{ $program->title }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state
                            icon="folder2-open"
                            title="No programmes found"
                            :message="request()->hasAny(['q', 'type', 'status'])
                                ? 'No programmes match these filters.'
                                : 'Aid programmes will be listed here once created.'">
                            @can('create', App\Models\AidProgram::class)
                                <x-slot:action>
                                    <a href="{{ route('aid-programs.create') }}" class="btn btn-sm btn-aidbridge">
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Create a programme
                                    </a>
                                </x-slot:action>
                            @endcan
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($programs->hasPages())
    <div class="mt-3">{{ $programs->withQueryString()->links() }}</div>
@endif
@endsection
