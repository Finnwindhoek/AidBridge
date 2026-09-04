{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
@extends('layouts.guest')
@section('title', 'Register')

@section('content')
    <h2 class="h5 mb-1">Create a beneficiary account</h2>
    <p class="text-muted small mb-4">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        Your NRIC is encrypted before it is stored and is never shown in full.
    </p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <h3 class="text-uppercase text-muted small fw-bold mb-3">Your details</h3>

        <div class="mb-3">
            <label for="name" class="form-label">
                Full name <span class="required" aria-hidden="true">*</span>
            </label>
            <input type="text" id="name" name="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-md-7 mb-3">
                <label for="email" class="form-label">
                    Email address <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="email" id="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autocomplete="username">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5 mb-3">
                <label for="phone" class="form-label">Phone <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" id="phone" name="phone" class="form-control"
                       value="{{ old('phone') }}" autocomplete="tel">
            </div>
        </div>

        <div class="row">
            <div class="col-md-7 mb-3">
                <label for="nric" class="form-label">
                    NRIC <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="nric" name="nric"
                       class="form-control @error('nric') is-invalid @enderror"
                       value="{{ old('nric') }}" placeholder="900101-14-5566" required>
                @error('nric')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Used to verify your identity against the agency registry.</div>
            </div>
            <div class="col-md-5 mb-3">
                <label for="state" class="form-label">State</label>
                <select id="state" name="state" class="form-select @error('state') is-invalid @enderror">
                    <option value="">Select…</option>
                    @foreach (config('aidbridge.states') as $state)
                        <option value="{{ $state }}" @selected(old('state') === $state)>{{ $state }}</option>
                    @endforeach
                </select>
                @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <hr class="my-4">

        <h3 class="text-uppercase text-muted small fw-bold mb-3">Security</h3>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="password" class="form-label">
                    Password <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">At least 8 characters, with letters and numbers.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="password_confirmation" class="form-label">
                    Confirm password <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       class="form-control" required autocomplete="new-password">
            </div>
        </div>

        <hr class="my-4">

        <h3 class="text-uppercase text-muted small fw-bold mb-3">Household</h3>

        <div class="form-check mb-4">
            <input type="checkbox" id="is_disabled" name="is_disabled" value="1"
                   class="form-check-input" @checked(old('is_disabled'))>
            <label for="is_disabled" class="form-check-label">
                My household includes a registered person with disability (OKU)
                <span class="d-block form-text mt-0">This may increase your eligibility score.</span>
            </label>
        </div>

        <button type="submit" class="btn btn-aidbridge w-100">
            <i class="bi bi-person-plus" aria-hidden="true"></i> Create account
        </button>
    </form>

    <hr class="my-4">

    <p class="text-center mb-0 small">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
