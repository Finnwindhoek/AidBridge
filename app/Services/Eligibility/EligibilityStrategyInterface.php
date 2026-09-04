<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 3 — Verification & Eligibility Assessment
 * Author: Chia Yi Kuang
 */

namespace App\Services\Eligibility;

use App\Models\Application;

/**
 * STRATEGY PATTERN — Module 3.
 *
 * Each assessment formula is a class implementing this contract. Adding a new
 * rule (say, a single-parent uplift) means writing one more implementation and
 * registering it — no existing strategy or the service itself is edited, which is
 * the Open/Closed Principle in practice.
 */
interface EligibilityStrategyInterface
{
    /** Stable identifier stored in the application's eligibility breakdown. */
    public function name(): string;

    /** Whether this strategy has anything to say about the given application. */
    public function appliesTo(Application $application): bool;

    /**
     * Relative influence of this strategy when several apply to one application.
     * Higher weights pull the blended score harder.
     */
    public function weight(): float;

    public function assess(Application $application): EligibilityResult;
}
