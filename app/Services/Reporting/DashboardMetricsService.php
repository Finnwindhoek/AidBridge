<?php

namespace App\Services\Reporting;

use App\Enums\ApplicationStatus;
use App\Enums\DisbursementStatus;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\Disbursement;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the figures behind the admin dashboard and the Chart.js JSON
 * endpoints. Kept out of the controller so the same numbers back both the Blade
 * view and the REST API.
 */
class DashboardMetricsService
{
    /** Headline counters shown across the top of the dashboard. */
    public function headline(): array
    {
        $applications = Application::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(status IN ('submitted','under_review')) as pending,
                SUM(status = 'approved') as approved,
                SUM(status = 'rejected') as rejected
            ")
            ->first();

        $disbursed = (float) Disbursement::settled()->sum('amount');

        $budget = AidProgram::query()
            ->selectRaw('COALESCE(SUM(budget_allocated),0) as allocated, COALESCE(SUM(budget_remaining),0) as remaining')
            ->first();

        $approvedCount = (int) ($applications->approved ?? 0);
        $decidedCount = $approvedCount + (int) ($applications->rejected ?? 0);

        return [
            'total_applications' => (int) ($applications->total ?? 0),
            'pending_applications' => (int) ($applications->pending ?? 0),
            'approved_applications' => $approvedCount,
            'rejected_applications' => (int) ($applications->rejected ?? 0),
            // Approval rate is meaningless until something has been decided.
            'approval_rate' => $decidedCount > 0 ? round($approvedCount / $decidedCount * 100, 1) : 0.0,
            'total_disbursed' => $disbursed,
            'budget_allocated' => (float) ($budget->allocated ?? 0),
            'budget_remaining' => (float) ($budget->remaining ?? 0),
            'active_programmes' => AidProgram::acceptingApplications()->count(),
            'beneficiaries_paid' => Disbursement::settled()->distinct('application_id')->count('application_id'),
        ];
    }

    /** Doughnut chart: application volume by status. */
    public function applicationsByStatus(): array
    {
        $counts = Application::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $values = [];

        foreach (ApplicationStatus::cases() as $case) {
            $labels[] = $case->label();
            $values[] = (int) ($counts[$case->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Line chart: submissions vs approvals over the trailing N months. */
    public function monthlyTrend(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = Application::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('COUNT(*) as submitted')
            ->selectRaw("SUM(status = 'approved') as approved")
            ->where('created_at', '>=', $start)
            ->groupBy('period')
            // keyBy, not pluck: the loop below needs the whole row (both counts)
            // indexed by period, and pluck(null, ...) yields null values.
            ->get()
            ->keyBy('period');

        $labels = [];
        $submitted = [];
        $approved = [];

        // Walk every month in the window so gaps render as zero rather than
        // collapsing the x-axis.
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $labels[] = $month->format('M Y');
            $submitted[] = (int) ($rows[$key]->submitted ?? 0);
            $approved[] = (int) ($rows[$key]->approved ?? 0);
        }

        return ['labels' => $labels, 'submitted' => $submitted, 'approved' => $approved];
    }

    /** Bar chart: budget utilisation per programme. */
    public function budgetUtilisation(): array
    {
        $programmes = AidProgram::query()
            ->whereIn('status', ['open', 'closed'])
            ->orderByDesc('budget_allocated')
            ->limit(8)
            ->get(['title', 'budget_allocated', 'budget_remaining']);

        return [
            'labels' => $programmes->pluck('title')->all(),
            'used' => $programmes->map(fn ($p) => round((float) $p->budget_allocated - (float) $p->budget_remaining, 2))->all(),
            'remaining' => $programmes->map(fn ($p) => round((float) $p->budget_remaining, 2))->all(),
        ];
    }

    /** Bar chart: aid distribution by state. */
    public function distributionByState(): array
    {
        $rows = Application::query()
            ->where('applications.status', ApplicationStatus::Approved->value)
            ->whereNotNull('state')
            ->select('state', DB::raw('COUNT(*) as total'))
            ->groupBy('state')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('state')->all(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** Disbursement pipeline: value and count sitting in each state. */
    public function disbursementPipeline(): array
    {
        $rows = Disbursement::query()
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount),0) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $labels = [];
        $counts = [];
        $totals = [];

        foreach (DisbursementStatus::cases() as $case) {
            $labels[] = $case->label();
            $counts[] = (int) ($rows[$case->value]->count ?? 0);
            $totals[] = round((float) ($rows[$case->value]->total ?? 0), 2);
        }

        return ['labels' => $labels, 'counts' => $counts, 'totals' => $totals];
    }

    /**
     * Aid distribution velocity: mean days from submission to money disbursed.
     */
    public function disbursementVelocity(): array
    {
        $row = Disbursement::query()
            ->join('applications', 'applications.id', '=', 'disbursements.application_id')
            ->whereNotNull('disbursements.disbursed_at')
            ->whereNotNull('applications.submitted_at')
            ->selectRaw('
                COUNT(*) as settled_count,
                AVG(TIMESTAMPDIFF(HOUR, applications.submitted_at, disbursements.disbursed_at)) as avg_hours,
                MIN(TIMESTAMPDIFF(HOUR, applications.submitted_at, disbursements.disbursed_at)) as min_hours,
                MAX(TIMESTAMPDIFF(HOUR, applications.submitted_at, disbursements.disbursed_at)) as max_hours
            ')
            ->first();

        return [
            'settled_count' => (int) ($row->settled_count ?? 0),
            'avg_days' => round((float) ($row->avg_hours ?? 0) / 24, 1),
            'fastest_days' => round((float) ($row->min_hours ?? 0) / 24, 1),
            'slowest_days' => round((float) ($row->max_hours ?? 0) / 24, 1),
        ];
    }

    /** Everything the dashboard needs, in one call. */
    public function all(): array
    {
        return [
            'headline' => $this->headline(),
            'applications_by_status' => $this->applicationsByStatus(),
            'monthly_trend' => $this->monthlyTrend(),
            'budget_utilisation' => $this->budgetUtilisation(),
            'distribution_by_state' => $this->distributionByState(),
            'disbursement_pipeline' => $this->disbursementPipeline(),
            'velocity' => $this->disbursementVelocity(),
        ];
    }
}
