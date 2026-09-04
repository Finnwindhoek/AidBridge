<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace App\Providers;

use App\Models\Application;
use App\Observers\ApplicationObserver;
use App\Repositories\DisbursementRepositoryInterface;
use App\Repositories\EloquentDisbursementRepository;
use App\Services\AuditLogger;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\Intents\ApplicationStatusIntent;
use App\Services\Chatbot\Intents\EligibilityIntent;
use App\Services\Chatbot\Intents\HowToApplyIntent;
use App\Services\Chatbot\Intents\PaymentStatusIntent;
use App\Services\Chatbot\Intents\PrivacySecurityIntent;
use App\Services\Chatbot\Intents\ProcessingTimeIntent;
use App\Services\Chatbot\Intents\ProgrammeListIntent;
use App\Services\Chatbot\Intents\RequiredDocumentsIntent;
use App\Services\Eligibility\EligibilityService;
use App\Services\Eligibility\Strategies\B40IncomeStrategy;
use App\Services\Eligibility\Strategies\DisabilitySupportStrategy;
use App\Services\Eligibility\Strategies\EmergencyReliefStrategy;
use App\Services\External\AgencyVerificationClient;
use App\Support\RequestContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
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

        /*
         * STRATEGY PATTERN — the help assistant.
         *
         * Same shape as the eligibility rules above. Order is priority: on a tie
         * the first intent listed answers, so the two that read the user's own
         * records come before the general informational ones.
         */
        $this->app->when(ChatbotService::class)
            ->needs('$intents')
            ->give(fn ($app) => [
                $app->make(ApplicationStatusIntent::class),
                $app->make(PaymentStatusIntent::class),
                $app->make(RequiredDocumentsIntent::class),
                $app->make(EligibilityIntent::class),
                $app->make(ProgrammeListIntent::class),
                $app->make(HowToApplyIntent::class),
                $app->make(ProcessingTimeIntent::class),
                $app->make(PrivacySecurityIntent::class),
            ]);

        /*
         * SINGLETON PATTERN.
         *
         * RequestContext enforces one-instance-per-request itself, through a
         * private constructor and getInstance(). It is aliased here so the same
         * object is returned to anything that type-hints it, rather than the
         * container building a second one behind the pattern's back.
         */
        $this->app->singleton(RequestContext::class, fn () => RequestContext::getInstance());

        /*
         * These two are stateless collaborators used on nearly every request. One
         * shared instance each avoids rebuilding them per injection point, and
         * keeps the audit trail's single write-point genuinely single.
         */
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(AgencyVerificationClient::class);
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

        /*
         * A queue worker is one long-lived process handling many jobs, so the
         * SINGLETON must be dropped between them — otherwise every job in the
         * worker's lifetime would share the correlation ID of the first.
         *
         * The `sync` driver is deliberately excluded: it runs the job inline inside
         * the request that dispatched it, so the job is part of that same action and
         * must keep its trace. Resetting there would split one action's audit rows
         * across two correlation IDs.
         */
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            if ($event->connectionName !== 'sync') {
                RequestContext::reset();
            }
        });

        /*
         * Laravel ships Tailwind pagination markup by default. The whole front end
         * is Bootstrap 5 and Tailwind is not loaded, so without this every paginator
         * renders as unstyled text.
         */
        Paginator::useBootstrapFive();

        // Behind XAMPP or any TLS-terminating proxy the app may see plain HTTP.
        // Forcing HTTPS in production keeps session cookies and signed document
        // URLs off the wire in clear text.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
