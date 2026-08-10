<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ReportController;
use App\Models\AidProgram;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API — consumed by the mobile client and the Chart.js dashboard
|--------------------------------------------------------------------------
| Stateless: every route below authenticates with a Sanctum bearer token, and
| token abilities mirror the user's role.
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

    /** Programmes currently open for application. */
    Route::get('/programmes', function () {
        return AidProgram::acceptingApplications()
            ->orderBy('title')
            ->get()
            ->map(fn (AidProgram $p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'type' => $p->type->value,
                'description' => $p->description,
                'payout_amount' => (float) $p->payout_amount,
                'income_threshold' => (float) $p->income_threshold,
                'closes_at' => $p->closes_at?->toDateString(),
            ]);
    })->name('api.programmes');

    /** The authenticated beneficiary's own applications. */
    Route::get('/applications', function (Request $request) {
        return $request->user()->applications()
            ->with(['aidProgram:id,slug,title', 'disbursement'])
            ->latest()
            ->get()
            ->map(fn (Application $a) => [
                'reference' => $a->reference,
                'programme' => $a->aidProgram->title,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'eligibility_score' => $a->eligibility_score,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
                'payment' => $a->disbursement ? [
                    'reference' => $a->disbursement->reference_code,
                    'amount' => (float) $a->disbursement->amount,
                    'status' => $a->disbursement->status->value,
                ] : null,
            ]);
    })->name('api.applications');

    /** Single application, guarded by the same policy as the web route. */
    Route::get('/applications/{application}', function (Application $application) {
        abort_unless(request()->user()->can('view', $application), 403);

        return [
            'reference' => $application->reference,
            'programme' => $application->aidProgram->title,
            'status' => $application->status->value,
            'household_income' => (float) $application->household_income,
            'dependents_count' => $application->dependents_count,
            'eligibility_score' => $application->eligibility_score,
            'eligibility_breakdown' => $application->eligibility_breakdown,
            'documents' => $application->documents->map(fn ($d) => [
                'type' => $d->document_type,
                'name' => $d->original_name,
                'verified' => $d->isVerified(),
            ]),
        ];
    })->name('api.applications.show');

    /*
     * Dashboard metrics for Chart.js. The `admin` ability is checked in addition
     * to the role, so a beneficiary token cannot read aggregate data even if the
     * account were later promoted.
     */
    Route::get('/reports/metrics', [ReportController::class, 'metrics'])
        ->middleware(['role:admin', 'abilities:admin'])
        ->name('api.reports.metrics');
});
