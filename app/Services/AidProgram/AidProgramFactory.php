<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 1 — Aid Programme Management
 * Author: Liong Ka Kien
 */

namespace App\Services\AidProgram;

use App\Enums\AidProgramType as AidProgramTypeEnum;
use App\Models\AidProgram;
use App\Services\AidProgram\Types\AidProgramType;
use App\Services\AidProgram\Types\CashDisbursementProgram;
use App\Services\AidProgram\Types\EmergencyGrantProgram;
use App\Services\AidProgram\Types\VoucherProgram;
use InvalidArgumentException;

/**
 * FACTORY PATTERN — Module 1.
 *
 * Turns a programme type discriminator into the concrete configuration object
 * that owns that type's defaults, payout formula and document requirements.
 * Callers ask the factory for behaviour instead of branching on a string.
 */
class AidProgramFactory
{
    /** The single registry of known programme types. */
    private const REGISTRY = [
        AidProgramTypeEnum::CashDisbursement->value => CashDisbursementProgram::class,
        AidProgramTypeEnum::Voucher->value => VoucherProgram::class,
        AidProgramTypeEnum::EmergencyGrant->value => EmergencyGrantProgram::class,
    ];

    /** Builds the configuration object for a raw type value or enum case. */
    public function make(AidProgramTypeEnum|string $type): AidProgramType
    {
        $key = $type instanceof AidProgramTypeEnum ? $type->value : $type;

        if (! isset(self::REGISTRY[$key])) {
            throw new InvalidArgumentException("Unknown aid programme type [{$key}].");
        }

        $class = self::REGISTRY[$key];

        return new $class();
    }

    /** Rebuilds the configuration object for a persisted programme row. */
    public function forProgram(AidProgram $program): AidProgramType
    {
        return $this->make($program->type);
    }

    /**
     * Type options for admin dropdowns, as value => label.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach (array_keys(self::REGISTRY) as $value) {
            $options[$value] = $this->make($value)->label();
        }

        return $options;
    }

    /** Defaults for the create form, so the UI can prefill when a type is picked. */
    public function defaultsFor(AidProgramTypeEnum|string $type): array
    {
        return $this->make($type)->defaults();
    }
}
