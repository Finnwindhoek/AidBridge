@extends('layouts.app')
@section('title', 'Edit Programme')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-9">

<h1 class="h4 mb-1">Edit programme</h1>
<p class="text-muted small mb-3">
    Type is fixed at <span class="badge bg-light text-dark">{{ $program->type->label() }}</span>.
    Changing the total budget adjusts the remaining balance by the same amount.
</p>

<form method="POST" action="{{ route('aid-programs.update', $program) }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-body">

        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" id="title" name="title"
                   class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $program->title) }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3"
                      class="form-control">{{ old('description', $program->description) }}</textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="budget_allocated" class="form-label">Total budget (RM)</label>
                <input type="number" step="0.01" min="0" id="budget_allocated" name="budget_allocated"
                       class="form-control @error('budget_allocated') is-invalid @enderror"
                       value="{{ old('budget_allocated', $program->budget_allocated) }}" required>
                @error('budget_allocated')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">
                    Committed so far: RM {{ number_format($program->budget_used, 2) }}
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="payout_amount" class="form-label">Base payout (RM)</label>
                <input type="number" step="0.01" min="0" id="payout_amount" name="payout_amount"
                       class="form-control" value="{{ old('payout_amount', $program->payout_amount) }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="income_threshold" class="form-label">Income threshold (RM)</label>
                <input type="number" step="0.01" min="0" id="income_threshold" name="income_threshold"
                       class="form-control" value="{{ old('income_threshold', $program->income_threshold) }}">
            </div>
            <div class="col-md-6 mb-3">
                <label for="min_dependents" class="form-label">Minimum dependents</label>
                <input type="number" min="0" max="20" id="min_dependents" name="min_dependents"
                       class="form-control" value="{{ old('min_dependents', $program->min_dependents) }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}"
                            @selected(old('status', $program->status->value) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="opens_at" class="form-label">Opens on</label>
                <input type="date" id="opens_at" name="opens_at" class="form-control"
                       value="{{ old('opens_at', $program->opens_at?->toDateString()) }}">
            </div>
            <div class="col-md-4 mb-3">
                <label for="closes_at" class="form-label">Closes on</label>
                <input type="date" id="closes_at" name="closes_at"
                       class="form-control @error('closes_at') is-invalid @enderror"
                       value="{{ old('closes_at', $program->closes_at?->toDateString()) }}">
                @error('closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

    </div>
    <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('aid-programs.show', $program) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-aidbridge">Save changes</button>
    </div>
</form>

<div class="card mt-3 border-danger-subtle">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong class="d-block">Retire this programme</strong>
            <span class="text-muted small">
                Archiving keeps all history. Deletion is only possible while no applications exist.
            </span>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('aid-programs.archive', $program) }}">
                @csrf @method('PATCH')
                <button class="btn btn-outline-secondary btn-sm">Archive</button>
            </form>
            @can('delete', $program)
                <form method="POST" action="{{ route('aid-programs.destroy', $program) }}"
                      onsubmit="return confirm('Permanently delete this programme?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                </form>
            @endcan
        </div>
    </div>
</div>

</div>
</div>
@endsection
