@extends('layouts.app')
@section('title', 'Edit Application')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">

<h1 class="h4 mb-1">Edit draft application</h1>
<p class="text-muted small mb-3">
    {{ $application->aidProgram->title }} · <span class="mono">{{ $application->reference }}</span>
</p>

<form method="POST" action="{{ route('applications.update', $application) }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-body">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="household_income" class="form-label">Gross monthly household income (RM)</label>
                <input type="number" step="0.01" min="0" id="household_income" name="household_income"
                       class="form-control @error('household_income') is-invalid @enderror"
                       value="{{ old('household_income', $application->household_income) }}" required>
                @error('household_income')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="dependents_count" class="form-label">Number of dependents</label>
                <input type="number" min="0" max="20" id="dependents_count" name="dependents_count"
                       class="form-control @error('dependents_count') is-invalid @enderror"
                       value="{{ old('dependents_count', $application->dependents_count) }}" required>
                @error('dependents_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="state" class="form-label">State</label>
            <select id="state" name="state" class="form-select" required>
                @foreach (['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis',
                           'Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur',
                           'Labuan','Putrajaya'] as $state)
                    <option value="{{ $state }}"
                        @selected(old('state', $application->state) === $state)>{{ $state }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" id="is_disaster_victim" name="is_disaster_victim" value="1"
                   class="form-check-input" @checked(old('is_disaster_victim', $application->is_disaster_victim))>
            <label for="is_disaster_victim" class="form-check-label">
                My household is affected by a declared disaster
            </label>
        </div>

        <div class="mb-0">
            <label for="notes" class="form-label">Additional notes</label>
            <textarea id="notes" name="notes" rows="3"
                      class="form-control">{{ old('notes', $application->notes) }}</textarea>
        </div>

    </div>
    <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('applications.show', $application) }}" class="btn btn-outline-secondary">Cancel</a>
        <div class="d-flex gap-2">
            @can('delete', $application)
                <form method="POST" action="{{ route('applications.destroy', $application) }}"
                      onsubmit="return confirm('Delete this draft? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger">Delete draft</button>
                </form>
            @endcan
            <button type="submit" class="btn btn-aidbridge">Save changes</button>
        </div>
    </div>
</form>

</div>
</div>
@endsection
