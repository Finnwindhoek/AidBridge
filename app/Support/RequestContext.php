<?php

namespace App\Support;

use Illuminate\Support\Str;
use LogicException;

/**
 * SINGLETON PATTERN — cross-cutting.
 *
 * Exactly one context exists for the lifetime of a request (or console command),
 * and it is the single source of the correlation ID stamped onto every audit row.
 *
 * Why this genuinely needs to be a singleton rather than an injected service:
 * one admin click fans out across several classes — EligibilityService writes
 * `application.assessed`, ApplicationObserver writes `application.status_changed`,
 * DisbursementService writes `disbursement.created`. None of them know about each
 * other, and threading an ID through every constructor would couple them all to a
 * concern none of them own. A single shared instance lets each write pick up the
 * same ID independently, so the audit trail can be grouped back into the one
 * action that caused it.
 *
 * The implementation is the textbook form: a private constructor and a private
 * clone so no caller can build a second instance, __wakeup() barred so
 * unserialisation cannot smuggle one in, and access only through getInstance().
 */
final class RequestContext
{
    private static ?self $instance = null;

    /** Shared by every audit row written during this request. */
    private readonly string $correlationId;

    /** Set once the actor is known; audit writes fall back to auth() otherwise. */
    private ?int $actorId = null;

    /** Private: the only way to obtain the instance is getInstance(). */
    private function __construct()
    {
        $this->correlationId = (string) Str::uuid();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Drops the current instance so the next getInstance() call mints a fresh
     * correlation ID.
     *
     * A normal PHP-FPM request gets a fresh process anyway, so this exists for the
     * two cases that do not: the test suite, which runs many requests in one
     * process, and long-lived workers (queue, Octane) which must not leak one
     * request's correlation ID into the next.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function actorId(): ?int
    {
        return $this->actorId ?? auth()->id();
    }

    /** Pins the acting user for writes that happen outside an auth guard. */
    public function withActor(?int $userId): self
    {
        $this->actorId = $userId;

        return $this;
    }

    /** Singletons are not copyable — a clone would defeat the whole point. */
    private function __clone() {}

    /** Nor restorable from a serialised payload. */
    public function __wakeup(): void
    {
        throw new LogicException('RequestContext is a singleton and cannot be unserialised.');
    }
}
