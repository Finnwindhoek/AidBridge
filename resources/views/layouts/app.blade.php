<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Exposed for any AJAX POST; Blade escapes it automatically. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AidBridge') — AidBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --ab-navy: #10243f; --ab-teal: #0d7d7d; }
        body { background: #f4f6f9; }
        .navbar-brand { font-weight: 700; letter-spacing: -.02em; }
        .bg-aidbridge { background: var(--ab-navy); }
        .text-aidbridge { color: var(--ab-teal); }
        .btn-aidbridge { background: var(--ab-teal); border-color: var(--ab-teal); color: #fff; }
        .btn-aidbridge:hover { background: #0a6363; border-color: #0a6363; color: #fff; }
        .card { border: 1px solid #e3e8ef; box-shadow: 0 1px 2px rgba(16,36,63,.05); }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1.1; }
        .table > :not(caption) > * > * { padding: .65rem .75rem; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85em; }
    </style>
    @stack('head')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-aidbridge mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('dashboard') }}">
            <i class="bi bi-bridge"></i> AidBridge
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            @auth
                <ul class="navbar-nav me-auto">
                    @if (auth()->user()->isAdmin())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active fw-semibold' : '' }}"
                               href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('aid-programs.*') ? 'active fw-semibold' : '' }}"
                               href="{{ route('aid-programs.index') }}">Programmes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('eligibility.*') ? 'active fw-semibold' : '' }}"
                               href="{{ route('eligibility.queue') }}">Review Queue</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('disbursements.*') ? 'active fw-semibold' : '' }}"
                               href="{{ route('disbursements.index') }}">Disbursements</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.applications') ? 'active fw-semibold' : '' }}"
                               href="{{ route('reports.applications') }}">Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.audit') ? 'active fw-semibold' : '' }}"
                               href="{{ route('reports.audit') }}">Audit Trail</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('applications.*') ? 'active fw-semibold' : '' }}"
                               href="{{ route('applications.index') }}">My Applications</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('aid-programs.*') ? 'active fw-semibold' : '' }}"
                               href="{{ route('aid-programs.index') }}">Browse Programmes</a>
                        </li>
                    @endif
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                            <span class="badge bg-light text-dark ms-1">{{ auth()->user()->role->label() }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                {{-- Logout is a POST so it cannot be triggered by a crafted link. --}}
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right"></i> Sign out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            @endauth
        </div>
    </div>
</nav>

<main class="container pb-5">
    @include('partials.alerts')
    @yield('content')
</main>

<footer class="border-top py-3 mt-auto">
    <div class="container text-center text-muted small">
        AidBridge — B40 Financial Aid Management &middot; {{ now()->year }}
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
