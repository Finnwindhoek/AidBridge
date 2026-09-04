<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 5 — Reporting & Monitoring
 * Author: Ng Yu Xun
 */

namespace App\Http\Controllers;

use App\Enums\AidProgramType;
use App\Enums\ApplicationStatus;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\Reporting\ApplicationReportBuilder;
use App\Services\Reporting\DashboardMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module 5. Admin-only: these views aggregate across all beneficiaries.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function dashboard(): View
    {
        return view('reports.dashboard', ['metrics' => $this->metrics->all()]);
    }

    /** JSON feed consumed by Chart.js for live dashboard refreshes. */
    public function metrics(): JsonResponse
    {
        return response()->json($this->metrics->all());
    }

    /** Filtered analytical report, assembled by the Builder. */
    public function applications(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $builder = ApplicationReportBuilder::make()->applyFilters($filters);

        return view('reports.applications', [
            'applications' => $builder->paginate(20),
            'summary' => $builder->summary(),
            'appliedFilters' => $builder->appliedFilters(),
            'filters' => $filters,
            'statusOptions' => ApplicationStatus::cases(),
            'typeOptions' => AidProgramType::cases(),
            'programmes' => AidProgram::orderBy('title')->get(['slug', 'title']),
            'states' => Application::whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
        ]);
    }

    /** Streamed CSV so a large export never builds the whole file in memory. */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $builder = ApplicationReportBuilder::make()->applyFilters($filters);

        $this->auditLogger->log('report.exported', null, ['format' => 'csv', 'filters' => $filters]);

        $filename = 'aidbridge-applications-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($builder) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Reference', 'Applicant', 'Programme', 'Programme Type', 'Status',
                'State', 'Household Income (RM)', 'Dependents', 'Eligibility Score',
                'Submitted At', 'Decided At', 'Disbursement Ref', 'Amount (RM)', 'Payment Status',
            ]);

            foreach ($builder->cursor() as $application) {
                fputcsv($handle, [
                    $application->reference,
                    // The applicant's NRIC is deliberately not exported.
                    $application->user?->name,
                    $application->aidProgram?->title,
                    $application->aidProgram?->type->label(),
                    $application->status->label(),
                    $application->state,
                    number_format((float) $application->household_income, 2, '.', ''),
                    $application->dependents_count,
                    $application->eligibility_score,
                    $application->submitted_at?->toDateTimeString(),
                    $application->decided_at?->toDateTimeString(),
                    $application->disbursement?->reference_code,
                    $application->disbursement ? number_format((float) $application->disbursement->amount, 2, '.', '') : null,
                    $application->disbursement?->status->label(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $builder = ApplicationReportBuilder::make()->applyFilters($filters);

        // PDF rendering holds the whole set in memory, so the export is capped.
        $applications = $builder->toQuery()->limit(500)->get();

        $this->auditLogger->log('report.exported', null, ['format' => 'pdf', 'filters' => $filters]);

        $pdf = Pdf::loadView('reports.pdf.applications', [
            'applications' => $applications,
            'summary' => $builder->summary(),
            'appliedFilters' => $builder->appliedFilters(),
            'generatedAt' => now(),
            'generatedBy' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('aidbridge-applications-'.now()->format('Ymd-His').'.pdf');
    }

    /** Audit trail viewer, for the compliance side of the assignment. */
    public function auditTrail(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user'), fn ($q) => $q->whereHas(
                'user',
                fn ($u) => $u->where('email', 'like', '%'.$request->string('user').'%')
            ))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('reports.audit', [
            'logs' => $logs,
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /**
     * Validates every filter before it reaches the Builder. Combined with the
     * Builder's own parameter binding and column allow-list, this is what makes
     * a user-driven report query safe.
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'string', 'in:draft,submitted,under_review,approved,rejected,withdrawn'],
            'state' => ['nullable', 'string', 'max:60'],
            'programme' => ['nullable', 'string', 'exists:aid_programs,slug'],
            'programme_type' => ['nullable', 'string', 'in:cash_disbursement,voucher,emergency_grant'],
            'min_dependents' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_income' => ['nullable', 'numeric', 'min:0'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'decided_from' => ['nullable', 'date'],
            'decided_to' => ['nullable', 'date', 'after_or_equal:decided_from'],
            'funded_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
    }
}
