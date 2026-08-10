@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php $h = $metrics['headline']; @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Monitoring dashboard</h1>
        <p class="text-muted small mb-0">Live aid distribution metrics across all programmes.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.applications') }}" class="btn btn-outline-secondary btn-sm">Detailed reports</a>
        <button id="refreshBtn" class="btn btn-aidbridge btn-sm"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
</div>

{{-- Headline counters --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="small text-muted">Total applications</div>
            <div class="stat-value">{{ number_format($h['total_applications']) }}</div>
            <div class="small text-muted">{{ number_format($h['pending_applications']) }} awaiting decision</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="small text-muted">Approval rate</div>
            <div class="stat-value text-success">{{ $h['approval_rate'] }}%</div>
            <div class="small text-muted">{{ number_format($h['approved_applications']) }} approved</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="small text-muted">Total disbursed</div>
            <div class="stat-value text-aidbridge">RM {{ number_format($h['total_disbursed'], 0) }}</div>
            <div class="small text-muted">{{ number_format($h['beneficiaries_paid']) }} beneficiaries paid</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="small text-muted">Budget remaining</div>
            <div class="stat-value">RM {{ number_format($h['budget_remaining'], 0) }}</div>
            <div class="small text-muted">of RM {{ number_format($h['budget_allocated'], 0) }} allocated</div>
        </div></div>
    </div>
</div>

{{-- Velocity band --}}
@php $v = $metrics['velocity']; @endphp
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="small text-muted">Aid distribution velocity</div>
                <div class="fw-semibold fs-5">{{ $v['avg_days'] }} days</div>
                <div class="small text-muted">average, submission → payment</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Fastest</div>
                <div class="fw-semibold fs-5">{{ $v['fastest_days'] }} days</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Slowest</div>
                <div class="fw-semibold fs-5">{{ $v['slowest_days'] }} days</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Payments settled</div>
                <div class="fw-semibold fs-5">{{ number_format($v['settled_count']) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Applications over time</div>
            <div class="card-body"><canvas id="trendChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">By status</div>
            <div class="card-body"><canvas id="statusChart" height="200"></canvas></div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Budget utilisation by programme</div>
            <div class="card-body"><canvas id="budgetChart" height="170"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Approved applications by state</div>
            <div class="card-body"><canvas id="stateChart" height="170"></canvas></div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Disbursement pipeline (RM)</div>
            <div class="card-body"><canvas id="pipelineChart" height="80"></canvas></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Initial data is rendered server-side and JSON-encoded by Blade, which
    // escapes it safely for embedding in a script block.
    let metrics = @json($metrics);

    const palette = {
        teal: '#0d7d7d', navy: '#10243f', amber: '#d98324',
        green: '#2e7d5b', red: '#b3403a', grey: '#8d99ae', blue: '#3a6ea5',
    };

    Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.plugins.legend.labels.boxWidth = 12;

    const charts = {};

    function build() {
        charts.trend = new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: metrics.monthly_trend.labels,
                datasets: [
                    { label: 'Submitted', data: metrics.monthly_trend.submitted,
                      borderColor: palette.navy, backgroundColor: palette.navy + '22', fill: true, tension: .3 },
                    { label: 'Approved', data: metrics.monthly_trend.approved,
                      borderColor: palette.teal, backgroundColor: palette.teal + '22', fill: true, tension: .3 },
                ],
            },
            options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });

        charts.status = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: metrics.applications_by_status.labels,
                datasets: [{
                    data: metrics.applications_by_status.values,
                    backgroundColor: [palette.grey, palette.blue, palette.amber, palette.green, palette.red, palette.navy],
                }],
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
        });

        charts.budget = new Chart(document.getElementById('budgetChart'), {
            type: 'bar',
            data: {
                labels: metrics.budget_utilisation.labels,
                datasets: [
                    { label: 'Committed', data: metrics.budget_utilisation.used, backgroundColor: palette.teal },
                    { label: 'Remaining', data: metrics.budget_utilisation.remaining, backgroundColor: palette.grey },
                ],
            },
            options: {
                responsive: true,
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
            },
        });

        charts.state = new Chart(document.getElementById('stateChart'), {
            type: 'bar',
            data: {
                labels: metrics.distribution_by_state.labels,
                datasets: [{ label: 'Approved', data: metrics.distribution_by_state.values, backgroundColor: palette.navy }],
            },
            options: {
                indexAxis: 'y', responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });

        charts.pipeline = new Chart(document.getElementById('pipelineChart'), {
            type: 'bar',
            data: {
                labels: metrics.disbursement_pipeline.labels,
                datasets: [{ label: 'Value (RM)', data: metrics.disbursement_pipeline.totals,
                             backgroundColor: [palette.grey, palette.blue, palette.teal, palette.green, palette.red] }],
            },
            options: { responsive: true, plugins: { legend: { display: false } },
                       scales: { y: { beginAtZero: true } } },
        });
    }

    build();

    // Live refresh pulls the same figures from the JSON endpoint.
    document.getElementById('refreshBtn').addEventListener('click', async function () {
        this.disabled = true;
        try {
            const response = await fetch('{{ route('reports.metrics') }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Request failed');
            metrics = await response.json();

            Object.values(charts).forEach(c => c.destroy());
            build();
        } catch (e) {
            console.error('Metrics refresh failed', e);
        } finally {
            this.disabled = false;
        }
    });
</script>
@endpush
