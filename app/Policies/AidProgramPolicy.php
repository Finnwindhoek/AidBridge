<?php

namespace App\Policies;

use App\Models\AidProgram;
use App\Models\User;

class AidProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AidProgram $program): bool
    {
        // Beneficiaries only browse programmes that are actually live; drafts and
        // archived programmes are internal.
        return $user->isAdmin() || $program->isAcceptingApplications();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AidProgram $program): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AidProgram $program): bool
    {
        // A programme with applications attached is archived, never deleted, so the
        // financial history stays intact.
        return $user->isAdmin() && $program->applications()->doesntExist();
    }
}
