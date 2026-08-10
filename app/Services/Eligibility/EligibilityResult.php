<?php

namespace App\Services\Eligibility;

/**
 * Immutable value object returned by every eligibility strategy.
 *
 * Keeping the result in one shape means new strategies plug into the service and
 * the UI without either side changing.
 */
final class EligibilityResult
{
    /**
     * @param  int  $score  0-100 priority score; higher means greater need.
     * @param  bool  $eligible  Whether the applicant passes this strategy's gate.
     * @param  array<int, string>  $reasons  Human-readable justifications for the audit trail.
     */
    public function __construct(
        public readonly string $strategy,
        public readonly int $score,
        public readonly bool $eligible,
        public readonly array $reasons = [],
    ) {}

    public static function eligible(string $strategy, int $score, array $reasons = []): self
    {
        return new self($strategy, self::clamp($score), true, $reasons);
    }

    public static function ineligible(string $strategy, array $reasons = []): self
    {
        return new self($strategy, 0, false, $reasons);
    }

    /** Scores are always expressed on a 0-100 scale so strategies stay comparable. */
    private static function clamp(int $score): int
    {
        return max(0, min(100, $score));
    }

    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy,
            'score' => $this->score,
            'eligible' => $this->eligible,
            'reasons' => $this->reasons,
        ];
    }
}
