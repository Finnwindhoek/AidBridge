<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Services\Disbursement\States\DisbursementState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Disbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'reference_code',
        'amount',
        'status',
        'payment_channel',
    ];

    protected function casts(): array
    {
        return [
            'status' => DisbursementStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Disbursement $disbursement) {
            $disbursement->reference_code ??= self::generateReferenceCode();
        });
    }

    public static function generateReferenceCode(): string
    {
        return 'AB-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }

    public function getRouteKeyName(): string
    {
        return 'reference_code';
    }

    // ---------------------------------------------------------------------
    // State pattern entry point
    // ---------------------------------------------------------------------

    /**
     * Returns the behaviour object for the current status. All lifecycle rules
     * (what may transition where) live in those classes, not in controllers.
     */
    public function state(): DisbursementState
    {
        return $this->status->makeState();
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DisbursementStatus::Disbursed->value,
            DisbursementStatus::Reconciled->value,
        ]);
    }
}
