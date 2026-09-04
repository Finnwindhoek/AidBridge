<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Beneficiary = 'beneficiary';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Beneficiary => 'Beneficiary',
        };
    }
}
