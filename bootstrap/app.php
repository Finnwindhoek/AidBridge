<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,

            // Sanctum ships these but Laravel does not register them for you.
            // Without them any route using `abilities:` or `ability:` fails with
            // "Target class [abilities] does not exist" at request time.
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        // Interface Agreement: every JSON response from the REST API is returned
        // inside the agreed {status, timeStamp, requestID, data} envelope. Applied
        // to the whole group so a new endpoint cannot be published outside it.
        // Prepended so it is the outermost middleware: a rate-limit rejection or an
        // authentication failure raised by the middleware inside it is still
        // returned inside the envelope.
        $middleware->api(prepend: [
            \App\Http\Middleware\ApplyInterfaceAgreement::class,
        ]);

        // The payment gateway posts JSON from outside the session; it authenticates
        // with a signature header instead, so the CSRF token check cannot apply.
        $middleware->validateCsrfTokens(except: [
            'webhooks/payments',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
