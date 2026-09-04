{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <h2 class="h5 mb-1">Sign in to your account</h2>
    <p class="text-muted small mb-4">Enter your registered email address and password.</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf {{-- CSRF token required on every state-changing POST. --}}

        <div class="mb-3">
            <label for="email" class="form-label">
                Email address <span class="required" aria-hidden="true">*</span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                <input type="email" id="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">
                Password <span class="required" aria-hidden="true">*</span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="current-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-check mb-4">
            <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
            <label for="remember" class="form-check-label">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-aidbridge w-100">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign in
        </button>
    </form>

    <hr class="my-4">

    <p class="text-center mb-0 small">
        New to AidBridge? <a href="{{ route('register') }}">Register as a beneficiary</a>
    </p>
@endsection
