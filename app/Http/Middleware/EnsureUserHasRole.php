<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-Based Access Control gate, registered as the `role` middleware alias.
 *
 *   Route::middleware('role:admin')->group(...);
 *   Route::middleware('role:admin,beneficiary')->group(...);
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        $role = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;

        if (! in_array($role, $roles, true)) {
            // 403 rather than a redirect so API clients get a machine-readable
            // answer and the browser sees the standard forbidden page.
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
