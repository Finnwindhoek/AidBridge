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
 * Product interface for the Factory pattern.
 *
 * Each concrete type owns the defaults and payout rules for one kind of aid, so
 * introducing a new programme type means adding a class here rather than editing
 * a switch statement in the controller.
 */
abstract class AidProgramType
{
    abstract public function type(): AidProgramTypeEnum;

    /** Human label used in the admin UI. */
    abstract public function label(): string;

    /** Column defaults applied when an admin leaves a field blank. */
    abstract public function defaults(): array;

    /**
     * Computes the payout for one approved application. Types differ here: a flat
     * cash payment, a per-household voucher, or a scored emergency grant.
     */
    abstract public function calculatePayout(float $basePayout, int $dependents, int $eligibilityScore): float;

    /** Which documents the applicant must attach for this programme type. */
    public function requiredDocuments(): array
    {
        return ['nric', 'income_proof'];
    }

    /** Merges admin-supplied attributes over this type's defaults. */
    public function buildAttributes(array $attributes): array
    {
        $merged = array_merge($this->defaults(), array_filter(
            $attributes,
            fn ($value) => $value !== null && $value !== ''
        ));

        $merged['type'] = $this->type()->value;

        return $merged;
    }
}
