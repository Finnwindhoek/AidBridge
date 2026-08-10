<?php

namespace App\Services\Eligibility\Strategies;

use App\Models\Application;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\EligibilityStrategyInterface;

/**
 * The baseline B40 means test: gross household income against the programme's
 * threshold, adjusted for household size.
 *
 * This is the only strategy that can disqualify an applicant outright; the others
 * add priority on top of it.
 */
class B40IncomeStrategy implements EligibilityStrategyInterface
{
    /** Each dependent raises the effective threshold, since income is shared. */
    private const PER_DEPENDENT_ALLOWANCE = 400.00;

    public function name(): string
    {
        return 'b40_income';
    }

    /** Income is assessed for every application. */
    public function appliesTo(Application $application): bool
    {
        return true;
    }

    public function weight(): float
    {
        return 1.0;
    }

    public function assess(Application $application): EligibilityResult
    {
        $income = (float) $application->household_income;
        $dependents = (int) $application->dependents_count;
        $program = $application->aidProgram;

        $baseThreshold = (float) $program->income_threshold;
        $adjustedThreshold = $baseThreshold + ($dependents * self::PER_DEPENDENT_ALLOWANCE);

        $reasons = [sprintf(
            'Household income RM %s against an adjusted threshold of RM %s (base RM %s + %d dependents).',
            number_format($income, 2),
            number_format($adjustedThreshold, 2),
            number_format($baseThreshold, 2),
            $dependents,
        )];

        if ($program->min_dependents > 0 && $dependents < $program->min_dependents) {
            $reasons[] = "This programme requires at least {$program->min_dependents} dependent(s).";

            return EligibilityResult::ineligible($this->name(), $reasons);
        }

        if ($income > $adjustedThreshold) {
            $reasons[] = 'Income exceeds the B40 threshold for this programme.';

            return EligibilityResult::ineligible($this->name(), $reasons);
        }

        // The further below the threshold, the higher the need. An applicant at
        // zero income scores 100; one exactly at the threshold scores 0.
        $score = (int) round((1 - ($income / max($adjustedThreshold, 0.01))) * 100);

        $reasons[] = "Income-based need score: {$score}/100.";

        return EligibilityResult::eligible($this->name(), $score, $reasons);
    }
}
