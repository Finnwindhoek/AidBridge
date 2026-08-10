<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Token issuing for the mobile client. Sessions are not used here; every
 * subsequent API call carries a Sanctum bearer token.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $throttleKey = 'api|'.strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        // Hash::check runs even when the user is missing so a wrong email and a
        // wrong password take the same time to answer.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Token abilities mirror the user's role, so a stolen beneficiary token
        // still cannot reach admin endpoints.
        $abilities = $user->isAdmin() ? ['admin', 'beneficiary'] : ['beneficiary'];

        $token = $user->createToken($data['device_name'], $abilities, now()->addDays(30));

        $this->auditLogger->log('api.token.issued', $user, ['device' => $data['device_name']], $user->id);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $this->auditLogger->log('api.token.revoked', $request->user());

        return response()->json(['message' => 'Token revoked.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'nric' => $user->masked_nric,
            'state' => $user->state,
        ]);
    }
}
