<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\Integration\ApplicationDetailClient;
use App\Services\Integration\DashboardMetricsClient;
use App\Services\Integration\DisbursementSummaryClient;
use App\Services\Integration\EligibilityClient;
use App\Services\Integration\ProgrammeCatalogueClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Integration console — the operational view of module-to-module web services.
 *
 * Every module both exposes a service and consumes a sibling's, forming a
 * closed loop: 2 -> 1, 3 -> 2, 4 -> 3, 5 -> 4, 1 -> 5. This screen exercises
 * all five calls live and reports the Interface Agreement fields returned by
 * each, so integration health is observable rather than assumed.
 *
 * The calls are made here rather than inside each module's request path
 * deliberately. The modules currently share one deployment, so putting an HTTP
 * round trip inside every page load would add latency for no benefit; the
 * clients are what matter, and they are unchanged on the day the modules are
 * deployed separately.
 */
class IntegrationConsoleController extends Controller
{
    public function __construct(
        private readonly ProgrammeCatalogueClient $programmes,
        private readonly ApplicationDetailClient $applications,
        private readonly EligibilityClient $eligibility,
        private readonly DisbursementSummaryClient $disbursements,
        private readonly DashboardMetricsClient $metrics,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\Disbursement::class);

        $actor = $request->user();

        // A real, assessed application so the two per-application calls have a
        // meaningful subject.
        $subject = Application::query()
            ->whereNotNull('eligibility_score')
            ->latest('verified_at')
            ->first();

        $results = [
            $this->programmes->openProgrammes($actor),
            $subject ? $this->applications->detail($actor, $subject) : null,
            $subject ? $this->eligibility->forApplication($actor, $subject) : null,
            $this->disbursements->summary($actor),
            $this->metrics->budgetUtilisation($actor),
        ];

        return view('integration.index', [
            'results' => array_values(array_filter($results)),
            'subject' => $subject,
        ]);
    }
}
