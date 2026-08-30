<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Single write-point for the audit trail.
 *
 * Centralising this keeps request metadata (actor, IP, user agent) consistent and
 * means a caller can never accidentally log an un-redacted payload.
 *
 * The correlation ID comes from the RequestContext SINGLETON, so every row written
 * during one request — whoever writes it — carries the same value.
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
        $context = RequestContext::getInstance();

        return AuditLog::create([
            'user_id' => $userId ?? $context->actorId(),
            'action' => $action,
            'correlation_id' => $context->correlationId(),
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
