{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
{{--
    Standard page heading used by every screen.

    @param string      $title
    @param string|null $subtitle
    @param array|null  $breadcrumbs  [['label' => 'Programmes', 'url' => '...'], ['label' => 'Current']]
    @param slot        $actions      buttons rendered on the right
--}}
@props(['title', 'subtitle' => null, 'breadcrumbs' => null])

<div class="page-header">
    @if (!empty($breadcrumbs))
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb">
                @foreach ($breadcrumbs as $crumb)
                    @if (!empty($crumb['url']) && !$loop->last)
                        <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                    @endif
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1>{{ $title }}</h1>
            @if ($subtitle)
                <p class="page-subtitle">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="d-flex flex-wrap gap-2 no-print">{{ $actions }}</div>
        @endisset
    </div>
</div>
