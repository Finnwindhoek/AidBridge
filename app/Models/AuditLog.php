<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 5 — Reporting & Monitoring
 * Author: Ng Yu Xun
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'correlation_id',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** All rows written by the same request, i.e. one admin action end to end. */
    public function scopeCorrelatedWith($query, string $correlationId)
    {
        return $query->where('correlation_id', $correlationId);
    }

    /** Short model name for report tables, e.g. "Application" instead of the FQCN. */
    public function getSubjectLabelAttribute(): string
    {
        return $this->auditable_type ? class_basename($this->auditable_type) : 'System';
    }
}
