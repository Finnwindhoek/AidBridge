<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace App\Services\Integration;

/**
 * Outcome of one module-to-module web service call.
 *
 * Carries the Interface Agreement's tracking fields alongside the payload, so a
 * caller can log exactly which remote call produced the data it is acting on.
 */
final class IntegrationResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $status,       // S | F | E
        public readonly string $sourceModule,
        public readonly string $targetModule,
        public readonly string $function,
        public readonly string $url,
        public readonly string $requestId,
        public readonly ?string $timeStamp = null,
        public readonly array $data = [],
        public readonly ?string $error = null,
        public readonly ?int $httpStatus = null,
        public readonly ?float $durationMs = null,
    ) {}

    public static function success(array $context, array $data, string $timeStamp, int $httpStatus, float $durationMs): self
    {
        return new self(
            ok: true, status: 'S', sourceModule: $context['source'], targetModule: $context['target'],
            function: $context['function'], url: $context['url'], requestId: $context['requestId'],
            timeStamp: $timeStamp, data: $data, httpStatus: $httpStatus, durationMs: $durationMs,
        );
    }

    public static function failure(array $context, string $status, string $error, ?int $httpStatus = null, ?float $durationMs = null): self
    {
        return new self(
            ok: false, status: $status, sourceModule: $context['source'], targetModule: $context['target'],
            function: $context['function'], url: $context['url'], requestId: $context['requestId'],
            error: $error, httpStatus: $httpStatus, durationMs: $durationMs,
        );
    }

    public function summary(): string
    {
        return $this->ok
            ? "{$this->sourceModule} -> {$this->targetModule}: {$this->function} OK"
            : "{$this->sourceModule} -> {$this->targetModule}: {$this->function} failed ({$this->error})";
    }
}
