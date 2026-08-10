<?php

namespace App\Services\Eligibility\Strategies;

use App\Models\Application;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\EligibilityStrategyInterface;

/**
 * Adds priority weight for households with a registered disability, which carry
 * care costs that the income test alone does not capture.
 *
 * This strategy never disqualifies: it only raises the score.
 */
class DisabilitySupportStrategy implements EligibilityStrategyInterface
{
    private const BASE_UPLIFT = 60;
    private const CERTIFIED_UPLIFT = 25;
    private const PER_DEPENDENT_UPLIFT = 5;

    public function name(): string
    {
        return 'disability_support';
    }

    public function appliesTo(Application $application): bool
    {
        return (bool) $application->user->is_disabled;
    }

    public function weight(): float
    {
        return 0.6;
    }

    public function assess(Application $application): EligibilityResult
    {
        $score = self::BASE_UPLIFT;
        $reasons = ['Household includes a registered person with disability.'];

        // A verified certificate is worth more than a self-declared status.
        $hasCertificate = $application->documents
            ->where('document_type', 'disability_cert')
            ->whereNotNull('verified_at')
            ->isNotEmpty();

        if ($hasCertificate) {
            $score += self::CERTIFIED_UPLIFT;
            $reasons[] = 'Verified disability certificate on file.';
        } else {
            $reasons[] = 'No verified disability certificate; self-declared status only.';
        }

        $dependentUplift = min((int) $application->dependents_count, 3) * self::PER_DEPENDENT_UPLIFT;

        if ($dependentUplift > 0) {
            $score += $dependentUplift;
            $reasons[] = "Care burden uplift for dependents: +{$dependentUplift}.";
        }

        return EligibilityResult::eligible($this->name(), $score, $reasons);
    }
}
