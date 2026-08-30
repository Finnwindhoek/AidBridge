<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AidBridge — B40 financial aid management system.">

    <title>@yield('title', 'Sign in') — AidBridge</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/aidbridge.css') }}?v={{ filemtime(public_path('css/aidbridge.css')) }}" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="container">
        <div class="auth-card">
            <div class="text-center text-white mb-4">
                <h1 class="h3 fw-bold mb-1">
                    <i class="bi bi-bridge" aria-hidden="true"></i> AidBridge
                </h1>
                <p class="mb-0 opacity-75">B40 Financial Aid Management</p>
            </div>

            <div class="card">
                <div class="card-body p-4 p-sm-4">
                    @include('partials.alerts')
                    @yield('content')
                </div>
            </div>

            <p class="text-center text-white-50 small mt-4 mb-0">
                &copy; {{ now()->year }} AidBridge
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
