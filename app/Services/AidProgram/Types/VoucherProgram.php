<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 1 — Aid Programme Management
 * Author: Liong Ka Kien
 */

namespace App\Services\AidProgram\Types;

use App\Enums\AidProgramType as AidProgramTypeEnum;

/**
 * Goods vouchers, e.g. the Back-to-School Fund. Issued in fixed denominations, so
 * the payout is rounded down to a whole voucher.
 */
class VoucherProgram extends AidProgramType
{
    private const VOUCHER_DENOMINATION = 50.00;
    private const PER_DEPENDENT_VOUCHERS = 1;

    public function type(): AidProgramTypeEnum
    {
        return AidProgramTypeEnum::Voucher;
    }

    public function label(): string
    {
        return 'Voucher';
    }

    public function defaults(): array
    {
        return [
            'payout_amount' => 200.00,
            'income_threshold' => 5250.00,
            'min_dependents' => 1,
            'description' => 'Redeemable vouchers for essential goods at approved merchants.',
        ];
    }

    public function calculatePayout(float $basePayout, int $dependents, int $eligibilityScore): float
    {
        $total = $basePayout + ($dependents * self::PER_DEPENDENT_VOUCHERS * self::VOUCHER_DENOMINATION);

        // Vouchers cannot be part-issued, so snap down to the denomination.
        return round(floor($total / self::VOUCHER_DENOMINATION) * self::VOUCHER_DENOMINATION, 2);
    }

    public function requiredDocuments(): array
    {
        return ['nric', 'income_proof', 'household_proof'];
    }
}
