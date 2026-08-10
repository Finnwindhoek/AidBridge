<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger of payment-gateway callbacks. The unique idempotency_key is what makes
 * a retried webhook a no-op instead of a duplicate payout.
 */
class WebhookReceipt extends Model
{
    protected $fillable = [
        'idempotency_key',
        'source',
        'event_type',
        'disbursement_id',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }
}
