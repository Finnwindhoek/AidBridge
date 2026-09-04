<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /** Owner or admin may download; the signed URL alone is not sufficient. */
    public function view(User $user, Document $document): bool
    {
        return $user->isAdmin() || $user->id === $document->application->user_id;
    }

    public function create(User $user, Document $document): bool
    {
        return $user->id === $document->application->user_id;
    }

    public function delete(User $user, Document $document): bool
    {
        // Once the application leaves draft, its evidence is frozen for auditability.
        return $user->id === $document->application->user_id
            && $document->application->isEditable();
    }

    public function verify(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }
}
