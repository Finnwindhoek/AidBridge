<?php

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
