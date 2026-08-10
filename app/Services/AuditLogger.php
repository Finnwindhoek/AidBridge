<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Single write-point for the audit trail.
 *
 * Centralising this keeps request metadata (actor, IP, user agent) consistent and
 * means a caller can never accidentally log an un-redacted payload.
 */
class AuditLogger
{
    /**
     * Keys whose values are stripped before an audit payload is persisted. The
     * audit trail records that something changed, never the sensitive value.
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'nric',
        'nric_encrypted',
        'remember_token',
        'token',
        'api_token',
    ];

    public function log(string $action, ?Model $subject = null, array $payload = [], ?int $userId = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
            'payload' => $payload === [] ? null : $this->redact($payload),
        ]);
    }

    /** Recursively blanks sensitive keys at any depth of the payload. */
    private function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redact($value);
            } elseif (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
