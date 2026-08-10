<?php

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
}
