@extends('layouts.app')
@section('title', 'New Application')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">

<x-page-header
    title="Apply for aid"
    subtitle="This creates a draft. You can upload your documents and submit on the next screen."
    :breadcrumbs="[
        ['label' => 'My applications', 'url' => route('applications.index')],
        ['label' => 'New application'],
    ]" />

@if ($programmes->isEmpty())
    <div class="alert alert-info d-flex gap-2">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
        There are no programmes open to you right now — either none are accepting applications, or you have
        already applied to all of them. <a href="{{ route('applications.index') }}">View your applications</a>.
        </div>
    </div>
@else
<form method="POST" action="{{ route('applications.store') }}" class="card">
    @csrf
    <div class="card-body">

        <div class="mb-3">
            <label for="aid_program_slug" class="form-label">Programme <span class="required" aria-hidden="true">*</span></label>
            <select id="aid_program_slug" name="aid_program_slug"
                    class="form-select @error('aid_program_slug') is-invalid @enderror" required>
                <option value="">Select a programme…</option>
                @foreach ($programmes as $programme)
                    <option value="{{ $programme->slug }}"
                        @selected(old('aid_program_slug', $selected) === $programme->slug)>
                        {{ $programme->title }} ({{ $programme->type->label() }})
                    </option>
                @endforeach
            </select>
            @error('aid_program_slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="household_income" class="form-label">Gross monthly household income (RM) <span class="required" aria-hidden="true">*</span></label>
                <input type="number" step="0.01" min="0" id="household_income" name="household_income"
                       class="form-control @error('household_income') is-invalid @enderror"
                       value="{{ old('household_income') }}" required>
                @error('household_income')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Total income of everyone in your household, before deductions.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="dependents_count" class="form-label">Number of dependents <span class="required" aria-hidden="true">*</span></label>
                <input type="number" min="0" max="20" id="dependents_count" name="dependents_count"
                       class="form-control @error('dependents_count') is-invalid @enderror"
                       value="{{ old('dependents_count', 0) }}" required>
                @error('dependents_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="state" class="form-label">State <span class="required" aria-hidden="true">*</span></label>
            <select id="state" name="state" class="form-select @error('state') is-invalid @enderror" required>
                <option value="">Select…</option>
                @foreach (config('aidbridge.states') as $state)
                    <option value="{{ $state }}"
                        @selected(old('state', auth()->user()->state) === $state)>{{ $state }}</option>
                @endforeach
            </select>
            @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" id="is_disaster_victim" name="is_disaster_victim" value="1"
                   class="form-check-input" @checked(old('is_disaster_victim'))>
            <label for="is_disaster_victim" class="form-check-label">
                My household is affected by a declared disaster (flood, fire, landslide)
            </label>
            <div class="form-text">This fast-tracks your application for emergency relief scoring.</div>
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Additional notes <span class="text-muted">(optional)</span></label>
            <textarea id="notes" name="notes" rows="3" class="form-control"
                      placeholder="Anything the reviewer should know about your circumstances.">{{ old('notes') }}</textarea>
        </div>

        <div class="alert alert-secondary small mb-0">
            <i class="bi bi-shield-lock" aria-hidden="true"></i>
            Declaring false information is an offence. Your declared income is cross-checked against
            the national agency registry during assessment.
        </div>

    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('applications.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-aidbridge"><i class="bi bi-plus-lg" aria-hidden="true"></i> Create draft</button>
    </div>
</form>
@endif

</div>
</div>
@endsection
