<?php

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
