<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'state' => $data['state'] ?? null,
            'is_disabled' => (bool) ($data['is_disabled'] ?? false),
        ]);

        // Self-registration always yields a beneficiary. Admin accounts are created
        // by seeding or by an existing admin, never by an inbound request.
        $user->role = UserRole::Beneficiary;

        // Routed through the mutator so the NRIC lands encrypted.
        $user->nric = $data['nric'];

        $user->save();

        auth()->login($user);
        $request->session()->regenerate();

        $this->auditLogger->log('auth.registered', $user);

        return redirect()->route('dashboard')
            ->with('status', 'Welcome to AidBridge. Your account is ready.');
    }
}
