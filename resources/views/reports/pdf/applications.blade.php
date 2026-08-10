<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>AidBridge Application Report</title>
    <style>
        /* dompdf supports a limited CSS subset, so this is deliberately plain. */
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #10243f; }
        .meta { font-size: 8px; color: #666; margin-bottom: 10px; }
        .filters { background: #f2f4f7; padding: 6px 8px; margin-bottom: 10px; font-size: 8px; }
        .summary { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .summary td { border: 1px solid #dde2e8; padding: 5px 7px; }
        .summary .label { color: #666; font-size: 8px; display: block; }
        .summary .value { font-size: 12px; font-weight: bold; color: #0d7d7d; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #10243f; color: #fff; padding: 4px 5px; text-align: left; font-size: 8px; }
        table.data td { border-bottom: 1px solid #e3e8ef; padding: 4px 5px; }
        table.data tr:nth-child(even) td { background: #fafbfc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 7px; color: #888;
                  border-top: 1px solid #ddd; padding-top: 3px; }
    </style>
</head>
<body>

<div class="footer">
    AidBridge — Confidential. Contains personal data of aid applicants; handle under PDPA obligations.
    Generated {{ $generatedAt->format('d M Y H:i') }} by {{ $generatedBy }}.
</div>

<h1>AidBridge — Application Report</h1>
<div class="meta">
    Generated {{ $generatedAt->format('d F Y, H:i') }} &middot; Prepared by {{ $generatedBy }}
    &middot; {{ $applications->count() }} record(s) shown
</div>

@if ($appliedFilters)
    <div class="filters">
        <strong>Filters:</strong>
        @foreach ($appliedFilters as $label => $value)
            {{ $label }}: {{ $value }}@if (! $loop->last) &nbsp;|&nbsp; @endif
        @endforeach
    </div>
@endif

<table class="summary">
    <tr>
        <td><span class="label">Matching applications</span><span class="value">{{ number_format($summary['total']) }}</span></td>
        <td><span class="label">Average household income</span><span class="value">RM {{ number_format($summary['avg_income'], 2) }}</span></td>
        <td><span class="label">Average dependents</span><span class="value">{{ $summary['avg_dependents'] }}</span></td>
        <td><span class="label">Average eligibility score</span><span class="value">{{ $summary['avg_score'] }}</span></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Reference</th>
            <th>Applicant</th>
            <th>Programme</th>
            <th>State</th>
            <th class="right">Income (RM)</th>
            <th class="center">Deps</th>
            <th class="center">Score</th>
            <th>Status</th>
            <th>Decided</th>
            <th class="right">Paid (RM)</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($applications as $application)
        <tr>
            {{-- NRIC is intentionally excluded from exports. --}}
            <td>{{ Str::limit($application->reference, 13, '') }}</td>
            <td>{{ $application->user->name }}</td>
            <td>{{ $application->aidProgram->title }}</td>
            <td>{{ $application->state }}</td>
            <td class="right">{{ number_format((float) $application->household_income, 2) }}</td>
            <td class="center">{{ $application->dependents_count }}</td>
            <td class="center">{{ $application->eligibility_score ?? '-' }}</td>
            <td>{{ $application->status->label() }}</td>
            <td>{{ $application->decided_at?->format('d/m/Y') ?? '-' }}</td>
            <td class="right">
                {{ $application->disbursement ? number_format((float) $application->disbursement->amount, 2) : '-' }}
            </td>
        </tr>
    @empty
        <tr><td colspan="10" class="center">No applications match the selected filters.</td></tr>
    @endforelse
    </tbody>
</table>

</body>
</html>
