<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'document_type',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'checksum',
    ];

    /**
     * file_path points at the private disk and must never reach the browser as a
     * literal value; downloads go through a signed route instead.
     */
    protected $hidden = ['file_path', 'checksum'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'nric' => 'NRIC Scan',
            'income_proof' => 'Income Proof',
            'household_proof' => 'Household Proof',
            'disability_cert' => 'Disability Certificate',
            default => 'Other',
        };
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size_bytes;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
