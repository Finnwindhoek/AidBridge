<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 1 — Aid Programme Management
 * Author: Liong Ka Kien
 */

namespace App\Models;

use App\Enums\AidProgramStatus;
use App\Enums\AidProgramType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AidProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'type',
        'budget_allocated',
        'budget_remaining',
        'payout_amount',
        'income_threshold',
        'min_dependents',
        'status',
        'opens_at',
        'closes_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AidProgramType::class,
            'status' => AidProgramStatus::class,
            'budget_allocated' => 'decimal:2',
            'budget_remaining' => 'decimal:2',
            'payout_amount' => 'decimal:2',
            'income_threshold' => 'decimal:2',
            'min_dependents' => 'integer',
            'opens_at' => 'date',
            'closes_at' => 'date',
        ];
    }

    /** Routes resolve programmes by slug so numeric primary keys stay out of URLs. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /** Programmes a beneficiary may actually apply to right now. */
    public function scopeAcceptingApplications(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('status', AidProgramStatus::Open)
            ->where('budget_remaining', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', $today));
    }

    // ---------------------------------------------------------------------
    // Domain helpers
    // ---------------------------------------------------------------------

    public function isAcceptingApplications(): bool
    {
        if ($this->status !== AidProgramStatus::Open || $this->budget_remaining <= 0) {
            return false;
        }

        if ($this->opens_at && $this->opens_at->isFuture()) {
            return false;
        }

        return ! ($this->closes_at && $this->closes_at->isPast());
    }

    public function hasBudgetFor(float $amount): bool
    {
        return (float) $this->budget_remaining >= $amount;
    }

    public function getBudgetUsedAttribute(): float
    {
        return (float) $this->budget_allocated - (float) $this->budget_remaining;
    }

    public function getBudgetUsedPercentAttribute(): float
    {
        $allocated = (float) $this->budget_allocated;

        return $allocated > 0 ? round($this->budget_used / $allocated * 100, 1) : 0.0;
    }
}
