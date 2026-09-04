{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Module 1 — Aid Programme Management
    Author: Liong Ka Kien
--}}
@extends('layouts.app')
@section('title', 'New Programme')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-9">

<x-page-header
    title="Create an aid programme"
    subtitle="Choosing a type applies that type's defaults via AidProgramFactory. Anything you leave blank is filled in from those defaults."
    :breadcrumbs="[
        ['label' => 'Programmes', 'url' => route('aid-programs.index')],
        ['label' => 'New programme'],
    ]" />

<form method="POST" action="{{ route('aid-programs.store') }}" class="card">
    @csrf
    <div class="card-body">

        <div class="mb-3">
            <label for="type" class="form-label">Programme type <span class="required" aria-hidden="true">*</span></label>
            <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                <option value="">Select a type…</option>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">The type cannot be changed once the programme exists.</div>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">Title <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="title" name="title"
                   class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title') }}" placeholder="Monthly B40 Food Subsidy" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="budget_allocated" class="form-label">Total budget (RM)</label>
                <input type="number" step="0.01" min="0" id="budget_allocated" name="budget_allocated"
                       class="form-control @error('budget_allocated') is-invalid @enderror"
                       value="{{ old('budget_allocated') }}" required>
                @error('budget_allocated')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="payout_amount" class="form-label">Base payout per beneficiary (RM)</label>
                <input type="number" step="0.01" min="0" id="payout_amount" name="payout_amount"
                       class="form-control" value="{{ old('payout_amount') }}">
                <div class="form-text">Leave blank to use the type default.</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="income_threshold" class="form-label">Income threshold (RM)</label>
                <input type="number" step="0.01" min="0" id="income_threshold" name="income_threshold"
                       class="form-control" value="{{ old('income_threshold') }}">
                <div class="form-text">Household income ceiling used by the B40 means test.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="min_dependents" class="form-label">Minimum dependents</label>
                <input type="number" min="0" max="20" id="min_dependents" name="min_dependents"
                       class="form-control" value="{{ old('min_dependents') }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                    <option value="open" @selected(old('status') === 'open')>Open</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="opens_at" class="form-label">Opens on</label>
                <input type="date" id="opens_at" name="opens_at" class="form-control" value="{{ old('opens_at') }}">
            </div>
            <div class="col-md-4 mb-3">
                <label for="closes_at" class="form-label">Closes on</label>
                <input type="date" id="closes_at" name="closes_at"
                       class="form-control @error('closes_at') is-invalid @enderror" value="{{ old('closes_at') }}">
                @error('closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('aid-programs.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-aidbridge">Create programme</button>
    </div>
</form>

</div>
</div>
@endsection

@push('scripts')
<script>
    // Prefills the numeric fields from the chosen type's Factory defaults.
    // The payload is JSON-encoded by Blade, so it is safe to embed in a script block.
    const typeDefaults = @json($typeDefaults);

    document.getElementById('type').addEventListener('change', function () {
        const defaults = typeDefaults[this.value];
        if (!defaults) return;

        for (const [field, value] of Object.entries(defaults)) {
            const input = document.getElementById(field);
            // Never clobber something the admin has already typed.
            if (input && !input.value) input.value = value;
        }
    });
</script>
@endpush
