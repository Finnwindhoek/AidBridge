{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
{{--
    Filter bar used above every listing table.

    Wraps the caller's fields in a GET form and supplies the Filter/Reset pair, so
    the six listings do not each repeat the same form scaffolding.

    @param string      $action  route the form submits to (also the Reset target)
    @param string|null $count   result summary shown on the right, e.g. "24 results"
    @param slot        $default the filter fields, as Bootstrap grid columns
--}}
@props(['action', 'count' => null])

<div class="card mb-3 no-print">
    <div class="card-body py-3">
        <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
            {{ $slot }}

            <div class="col-md-auto d-flex gap-2 ms-md-auto">
                <button type="submit" class="btn btn-sm btn-secondary">
                    <i class="bi bi-funnel" aria-hidden="true"></i> Filter
                </button>
                <a href="{{ $action }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        @if ($count !== null)
            <div class="small text-muted mt-2 pt-2 border-top">{{ $count }}</div>
        @endif
    </div>
</div>
