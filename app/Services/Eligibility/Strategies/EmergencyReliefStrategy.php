<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 3 — Verification & Eligibility Assessment
 * Author: Chia Yi Kuang
 */

namespace App\Services\Eligibility\Strategies;

use App\Enums\AidProgramType;
use App\Models\Application;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\EligibilityStrategyInterface;

/**
 * Fast-tracks households affected by a declared disaster.
 *
 * Carries the highest weight of the uplift strategies, because in a flood response
 * speed of payout matters more than fine-grained income ranking.
 */
class EmergencyReliefStrategy implements EligibilityStrategyInterface
{
    private const BASE_PRIORITY = 80;
    private const EMERGENCY_PROGRAM_UPLIFT = 15;
    private const LARGE_HOUSEHOLD_THRESHOLD = 4;
    private const LARGE_HOUSEHOLD_UPLIFT = 5;

    public function name(): string
    {
        return 'emergency_relief';
    }

    public function appliesTo(Application $application): bool
    {
        return (bool) $application->is_disaster_victim;
    }

    public function weight(): float
    {
        return 0.9;
    }

    public function assess(Application $application): EligibilityResult
    {
        $score = self::BASE_PRIORITY;
        $reasons = ['Applicant is registered as affected by a declared disaster.'];

        if ($application->aidProgram->type === AidProgramType::EmergencyGrant) {
            $score += self::EMERGENCY_PROGRAM_UPLIFT;
            $reasons[] = 'Applying to a dedicated emergency grant programme.';
        }

        if ((int) $application->dependents_count >= self::LARGE_HOUSEHOLD_THRESHOLD) {
            $score += self::LARGE_HOUSEHOLD_UPLIFT;
            $reasons[] = 'Large displaced household.';
        }

        return EligibilityResult::eligible($this->name(), $score, $reasons);
    }
}
