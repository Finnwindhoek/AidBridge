<?php

namespace App\Services\AidProgram\Types;

use App\Enums\AidProgramType as AidProgramTypeEnum;

/**
 * Recurring cash aid, e.g. the Monthly B40 Food Subsidy. Flat payout with a small
 * per-dependent uplift.
 */
class CashDisbursementProgram extends AidProgramType
{
    private const PER_DEPENDENT_UPLIFT = 50.00;
    private const MAX_UPLIFT_DEPENDENTS = 5;

    public function type(): AidProgramTypeEnum
    {
        return AidProgramTypeEnum::CashDisbursement;
    }

    public function label(): string
    {
        return 'Cash Disbursement';
    }

    public function defaults(): array
    {
        return [
            'payout_amount' => 500.00,
            'income_threshold' => 5250.00,
            'min_dependents' => 0,
            'description' => 'Direct cash assistance credited to the beneficiary bank account.',
        ];
    }

    public function calculatePayout(float $basePayout, int $dependents, int $eligibilityScore): float
    {
        $uplift = min($dependents, self::MAX_UPLIFT_DEPENDENTS) * self::PER_DEPENDENT_UPLIFT;

        return round($basePayout + $uplift, 2);
    }
}
