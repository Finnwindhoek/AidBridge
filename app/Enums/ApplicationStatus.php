<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /** Bootstrap contextual colour used by the Blade status badges. */
    public function colour(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Submitted => 'info',
            self::UnderReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'dark',
        };
    }

    /** A beneficiary may still edit or delete the application in these states. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Terminal states never transition again. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Withdrawn], true);
    }
}
