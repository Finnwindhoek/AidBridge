<?php

use App\Http\Controllers\AidProgramController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Payment gateway webhook
|--------------------------------------------------------------------------
| Unauthenticated and CSRF-exempt by necessity; it authenticates with an HMAC
| signature and de-duplicates with an idempotency key instead. Rate limited so a
| leaked URL cannot be used to hammer the ledger.
*/
Route::post('/webhooks/payments', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.payments');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Role-aware landing page: admins get the analytics dashboard, beneficiaries
    // get their own application list.
    Route::get('/dashboard', function () {
        return auth()->user()->isAdmin()
            ? app(ReportController::class)->dashboard()
            : redirect()->route('applications.index');
    })->name('dashboard');

    /*
     * Help assistant.
     *
     * Throttled because it is the only endpoint that accepts free text from a
     * signed-in user and runs a scoring pass over every intent for each call.
     */
    Route::post('/assistant/ask', [AssistantController::class, 'ask'])
        ->middleware('throttle:30,1')
        ->name('assistant.ask');

    /*
     * Module 1 — Aid Programme Management
     */
    Route::get('/programmes', [AidProgramController::class, 'index'])->name('aid-programs.index');
    Route::get('/programmes/{aidProgram}', [AidProgramController::class, 'show'])->name('aid-programs.show');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/programmes/create', [AidProgramController::class, 'create'])->name('aid-programs.create');
        Route::post('/admin/programmes', [AidProgramController::class, 'store'])->name('aid-programs.store');
        Route::get('/admin/programmes/{aidProgram}/edit', [AidProgramController::class, 'edit'])->name('aid-programs.edit');
        Route::put('/admin/programmes/{aidProgram}', [AidProgramController::class, 'update'])->name('aid-programs.update');
        Route::delete('/admin/programmes/{aidProgram}', [AidProgramController::class, 'destroy'])->name('aid-programs.destroy');
        Route::patch('/admin/programmes/{aidProgram}/archive', [AidProgramController::class, 'archive'])->name('aid-programs.archive');
    });

    /*
     * Module 2 — Applications and Documents
     */
    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('/applications/{application}/edit', [ApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('/applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::delete('/applications/{application}', [ApplicationController::class, 'destroy'])->name('applications.destroy');
    Route::patch('/applications/{application}/submit', [ApplicationController::class, 'submit'])->name('applications.submit');
    Route::patch('/applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])->name('applications.withdraw');

    Route::post('/applications/{application}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::patch('/documents/{document}/verify', [DocumentController::class, 'verify'])
        ->middleware('role:admin')->name('documents.verify');

    // 'signed' proves the link was minted by us and has not been edited; the
    // DocumentPolicy then proves this particular user may read this file.
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('signed')->name('documents.download');

    /*
     * Modules 3-5 — administrator only
     */
    Route::middleware('role:admin')->group(function () {
        // Module 3 — Verification and Eligibility
        Route::get('/admin/review-queue', [EligibilityController::class, 'queue'])->name('eligibility.queue');
        Route::post('/admin/applications/{application}/assess', [EligibilityController::class, 'assess'])->name('eligibility.assess');
        Route::post('/admin/applications/{application}/decide', [EligibilityController::class, 'decide'])->name('eligibility.decide');

        // Module 4 — Fund Allocation and Disbursement
        Route::get('/admin/disbursements', [DisbursementController::class, 'index'])->name('disbursements.index');
        Route::post('/admin/applications/{application}/disbursement', [DisbursementController::class, 'store'])->name('disbursements.store');
        Route::patch('/admin/disbursements/{disbursement}/approve', [DisbursementController::class, 'approve'])->name('disbursements.approve');
        Route::patch('/admin/disbursements/{disbursement}/disburse', [DisbursementController::class, 'disburse'])->name('disbursements.disburse');
        Route::patch('/admin/disbursements/{disbursement}/reconcile', [DisbursementController::class, 'reconcile'])->name('disbursements.reconcile');
        Route::patch('/admin/disbursements/{disbursement}/fail', [DisbursementController::class, 'fail'])->name('disbursements.fail');

        // Module 5 — Reporting and Monitoring
        Route::get('/admin/reports', [ReportController::class, 'applications'])->name('reports.applications');
        Route::get('/admin/reports/metrics', [ReportController::class, 'metrics'])->name('reports.metrics');
        Route::get('/admin/reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
        Route::get('/admin/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('/admin/audit-trail', [ReportController::class, 'auditTrail'])->name('reports.audit');
    });

    // Beneficiaries may view their own payment record.
    Route::get('/disbursements/{disbursement}', [DisbursementController::class, 'show'])->name('disbursements.show');
});
