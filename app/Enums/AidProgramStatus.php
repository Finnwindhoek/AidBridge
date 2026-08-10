<?php

namespace App\Enums;

enum AidProgramStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Open => 'success',
            self::Closed => 'warning',
            self::Archived => 'dark',
        };
    }
}
