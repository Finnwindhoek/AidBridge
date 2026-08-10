<?php

namespace App\Providers;

use App\Models\Application;
use App\Observers\ApplicationObserver;
use App\Repositories\DisbursementRepositoryInterface;
use App\Repositories\EloquentDisbursementRepository;
use App\Services\Eligibility\EligibilityService;
use App\Services\Eligibility\Strategies\B40IncomeStrategy;
use App\Services\Eligibility\Strategies\DisabilitySupportStrategy;
use App\Services\Eligibility\Strategies\EmergencyReliefStrategy;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * REPOSITORY PATTERN (Module 4).
         *
         * Controllers and services type-hint the interface, so the concrete
         * Eloquent implementation can be swapped for a fake in tests without
         * touching a single caller.
         */
        $this->app->bind(DisbursementRepositoryInterface::class, EloquentDisbursementRepository::class);

        /*
         * STRATEGY PATTERN (Module 3).
         *
         * The set of eligibility rules is assembled here rather than hard-coded in
         * the service. Adding a rule means writing one class and adding one line.
         */
        $this->app->when(EligibilityService::class)
            ->needs('$strategies')
            ->give(fn ($app) => [
                $app->make(B40IncomeStrategy::class),
                $app->make(DisabilitySupportStrategy::class),
                $app->make(EmergencyReliefStrategy::class),
            ]);
    }

    public function boot(): void
    {
        /*
         * OBSERVER PATTERN (Module 2).
         *
         * Registered once here, so every write to an Application — web, API, queue
         * or console — produces the same audit trail and notifications.
         */
        Application::observe(ApplicationObserver::class);

        // Behind XAMPP or any TLS-terminating proxy the app may see plain HTTP.
        // Forcing HTTPS in production keeps session cookies and signed document
        // URLs off the wire in clear text.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
