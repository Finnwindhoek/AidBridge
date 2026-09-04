{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
{{--
    Dashboard counter tile.

    @param string      $label
    @param string      $value
    @param string|null $meta       supporting line under the figure
    @param string|null $valueClass extra classes for the figure, e.g. "text-success"
    @param string|null $icon
--}}
@props(['label', 'value', 'meta' => null, 'valueClass' => '', 'icon' => null])

<div class="card stat-card h-100">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div class="stat-label">{{ $label }}</div>
            @if ($icon)
                <i class="bi bi-{{ $icon }} text-muted" aria-hidden="true"></i>
            @endif
        </div>
        <div class="stat-value {{ $valueClass }}">{{ $value }}</div>
        @if ($meta)
            <div class="stat-meta">{{ $meta }}</div>
        @endif
    </div>
</div>
