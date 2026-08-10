@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <h2 class="h5 mb-3">Sign in to your account</h2>

    <form method="POST" action="{{ route('login') }}">
        @csrf {{-- CSRF token required on every state-changing POST. --}}

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" id="email" name="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   required autocomplete="current-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
            <label for="remember" class="form-check-label">Remember me</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>

    <hr class="my-4">
    <p class="text-center mb-0 small">
        New to AidBridge? <a href="{{ route('register') }}">Register as a beneficiary</a>
    </p>
@endsection
