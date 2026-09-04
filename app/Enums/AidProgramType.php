<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 1 — Aid Programme Management
 * Author: Liong Ka Kien
 */

namespace App\Enums;

enum AidProgramType: string
{
    case CashDisbursement = 'cash_disbursement';
    case Voucher = 'voucher';
    case EmergencyGrant = 'emergency_grant';

    public function label(): string
    {
        return match ($this) {
            self::CashDisbursement => 'Cash Disbursement',
            self::Voucher => 'Voucher',
            self::EmergencyGrant => 'Emergency Grant',
        };
    }

    /** Bootstrap Icons name used on programme cards and listings. */
    public function icon(): string
    {
        return match ($this) {
            self::CashDisbursement => 'cash-stack',
            self::Voucher => 'ticket-perforated',
            self::EmergencyGrant => 'exclamation-triangle-fill',
        };
    }
}
