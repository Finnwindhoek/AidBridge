<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        // Beneficiaries see their own list; admins see all. The query is scoped in
        // the controller, so both roles are allowed to reach the index.
        return true;
    }

    public function view(User $user, Application $application): bool
    {
        return $user->id === $application->user_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        // Admins administer programmes; they do not apply for aid themselves.
        return $user->isBeneficiary();
    }

    public function update(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && $application->isEditable();
    }

    public function delete(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && $application->isEditable();
    }

    public function submit(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && $application->isEditable();
    }

    public function withdraw(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && ! $application->status->isFinal();
    }

    /**
     * Reviewing, scoring and deciding are administrator-only actions.
     *
     * The application is optional so the same ability covers both a single record
     * and the review queue as a whole (authorize('review', Application::class)).
     */
    public function review(User $user, ?Application $application = null): bool
    {
        return $user->isAdmin();
    }

    public function decide(User $user, Application $application): bool
    {
        return $user->isAdmin() && ! $application->status->isFinal();
    }
}
