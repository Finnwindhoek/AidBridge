{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AidBridge — B40 financial aid management system.">
    {{-- Exposed for any AJAX POST; Blade escapes it automatically. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'AidBridge') — AidBridge</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Cache-busted so a stylesheet change is picked up without a hard refresh. --}}
    <link href="{{ asset('css/aidbridge.css') }}?v={{ filemtime(public_path('css/aidbridge.css')) }}" rel="stylesheet">

    @stack('head')
</head>
<body>

{{-- Lets keyboard users skip the navigation on every page. --}}
<a class="skip-link" href="#main-content">Skip to main content</a>

<nav class="navbar navbar-expand-lg navbar-dark bg-aidbridge mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <i class="bi bi-bridge" aria-hidden="true"></i>
            <span>AidBridge</span>
        </a>

        @auth
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#mainNav" aria-controls="mainNav"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @if (auth()->user()->isAdmin())
                        @php
                            // Nav is data-driven so the markup below stays one loop
                            // instead of six near-identical <li> blocks.
                            $links = [
                                ['route' => 'dashboard',            'match' => 'dashboard',            'label' => 'Dashboard',      'icon' => 'speedometer2'],
                                ['route' => 'aid-programs.index',   'match' => 'aid-programs.*',       'label' => 'Programmes',     'icon' => 'folder2-open'],
                                ['route' => 'eligibility.queue',    'match' => 'eligibility.*',        'label' => 'Review Queue',   'icon' => 'clipboard-check'],
                                ['route' => 'disbursements.index',  'match' => 'disbursements.*',      'label' => 'Disbursements',  'icon' => 'cash-coin'],
                                ['route' => 'reports.applications', 'match' => 'reports.applications', 'label' => 'Reports',        'icon' => 'file-earmark-bar-graph'],
                                ['route' => 'integration.index',    'match' => 'integration.*',        'label' => 'Integration',    'icon' => 'diagram-3'],
                                ['route' => 'reports.audit',        'match' => 'reports.audit',        'label' => 'Audit Trail',    'icon' => 'shield-lock'],
                            ];
                        @endphp
                    @else
                        @php
                            $links = [
                                ['route' => 'applications.index',  'match' => 'applications.*', 'label' => 'My Applications',  'icon' => 'file-earmark-text'],
                                ['route' => 'aid-programs.index',  'match' => 'aid-programs.*', 'label' => 'Browse Programmes', 'icon' => 'search'],
                            ];
                        @endphp
                    @endif

                    @foreach ($links as $link)
                        @php $isCurrent = request()->routeIs($link['match']); @endphp
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2 {{ $isCurrent ? 'active' : '' }}"
                               href="{{ route($link['route']) }}"
                               @if ($isCurrent) aria-current="page" @endif>
                                <i class="bi bi-{{ $link['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $link['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span>{{ auth()->user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <div class="dropdown-item-text">
                                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                                    <div class="small text-muted">{{ auth()->user()->email }}</div>
                                    <span class="badge badge-soft mt-1">{{ auth()->user()->role->label() }}</span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                {{-- Logout is a POST so it cannot be triggered by a crafted link. --}}
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        @endauth
    </div>
</nav>

<main class="container pb-5" id="main-content">
    @include('partials.alerts')
    @yield('content')
</main>

{{-- Beneficiaries only: every answer is drawn from the asker's own case, which
     an administrator does not have. --}}
@auth
    @if (auth()->user()->isBeneficiary())
        <x-assistant-widget :starters="app(App\Services\Chatbot\ChatbotService::class)->starterQuestions()" />
    @endif
@endauth

<footer class="footer border-top bg-white py-3">
    <div class="container d-flex flex-wrap justify-content-between gap-2 text-muted small">
        <span>AidBridge — B40 Financial Aid Management</span>
        <span>&copy; {{ now()->year }}</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
