<?php

namespace App\Services\AidProgram\Types;

use App\Enums\AidProgramType as AidProgramTypeEnum;

/**
 * Disaster response, e.g. Emergency Flood Relief. Payout scales with the
 * eligibility score so the worst-affected households receive more, and the income
 * threshold is relaxed because disasters cross income bands.
 */
class EmergencyGrantProgram extends AidProgramType
{
    private const MAX_SEVERITY_MULTIPLIER = 1.5;

    public function type(): AidProgramTypeEnum
    {
        return AidProgramTypeEnum::EmergencyGrant;
    }

    public function label(): string
    {
        return 'Emergency Grant';
    }

    public function defaults(): array
    {
        return [
            'payout_amount' => 1000.00,
            'income_threshold' => 7000.00,
            'min_dependents' => 0,
            'description' => 'One-off emergency relief for households affected by a declared disaster.',
        ];
    }

    public function calculatePayout(float $basePayout, int $dependents, int $eligibilityScore): float
    {
        // Score 0-100 maps onto a 1.0x - 1.5x multiplier.
        $multiplier = 1 + (max(0, min(100, $eligibilityScore)) / 100) * (self::MAX_SEVERITY_MULTIPLIER - 1);

        return round($basePayout * $multiplier, 2);
    }

    public function requiredDocuments(): array
    {
        return ['nric'];
    }
}
