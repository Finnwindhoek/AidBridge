<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') — AidBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(160deg, #10243f 0%, #0d7d7d 100%); min-height: 100vh; }
        .auth-card { max-width: 520px; margin: 4rem auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="auth-card">
        <div class="text-center text-white mb-4">
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-bridge"></i> AidBridge</h1>
            <p class="mb-0 opacity-75">B40 Financial Aid Management</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                @include('partials.alerts')
                @yield('content')
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
