<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'aid_program_id',
        'status',
        'household_income',
        'dependents_count',
        'state',
        'is_disaster_victim',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'household_income' => 'decimal:2',
            'dependents_count' => 'integer',
            'is_disaster_victim' => 'boolean',
            'eligibility_score' => 'integer',
            'eligibility_breakdown' => 'array',
            'agency_verification' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every application gets an unguessable public reference at creation.
        static::creating(function (Application $application) {
            $application->reference ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aidProgram(): BelongsTo
    {
        return $this->belongsTo(AidProgram::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function disbursement(): HasOne
    {
        return $this->hasOne(Disbursement::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeStatus(Builder $query, ApplicationStatus|string|null $status): Builder
    {
        return $status === null
            ? $query
            : $query->where('status', $status instanceof ApplicationStatus ? $status->value : $status);
    }

    public function scopeAwaitingDecision(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ApplicationStatus::Submitted->value,
            ApplicationStatus::UnderReview->value,
        ]);
    }

    // ---------------------------------------------------------------------
    // Domain helpers
    // ---------------------------------------------------------------------

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function hasRequiredDocuments(): bool
    {
        $present = $this->documents->pluck('document_type')->map(
            fn ($type) => $type instanceof \BackedEnum ? $type->value : $type
        );

        return $present->contains('nric') && $present->contains('income_proof');
    }
}
