<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 3 — Verification & Eligibility Assessment
 * Author: Chia Yi Kuang
 */

namespace App\Services\Eligibility;

use App\Models\Application;
use App\Services\AidProgram\AidProgramService;
use App\Services\AuditLogger;
use App\Services\External\AgencyVerificationClient;

/**
 * Context class for the Strategy pattern.
 *
 * Selects the strategies that apply to an application, runs each one, and blends
 * their scores into a single priority figure. The strategy list is injected by the
 * service container (see AppServiceProvider), so the set of rules is configuration
 * rather than hard-coded logic.
 */
class EligibilityService
{
    /** Applications scoring at or above this are recommended for approval. */
    public const RECOMMENDATION_THRESHOLD = 50;

    /**
     * @param  iterable<EligibilityStrategyInterface>  $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
        private readonly AgencyVerificationClient $agencyClient,
        private readonly AidProgramService $programService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Runs the full assessment and persists the outcome on the application.
     */
    public function assess(Application $application, bool $callExternalRegistry = true): array
    {
        $application->loadMissing(['aidProgram', 'user', 'documents']);

        $results = [];
        $eligible = true;

        foreach ($this->strategies as $strategy) {
            if (! $strategy->appliesTo($application)) {
                continue;
            }

            $result = $strategy->assess($application);
            $results[] = $result;

            // Any strategy may veto. In practice only the income means test does.
            if (! $result->eligible) {
                $eligible = false;
            }
        }

        $score = $eligible ? $this->blend($results) : 0;

        $verification = $callExternalRegistry
            ? $this->agencyClient->verify($application)
            : ($application->agency_verification ?? null);

        // A registry discrepancy does not auto-reject, but it must be visible to
        // the reviewing officer.
        $flagged = ($verification['status'] ?? null) === 'discrepancy';

        $breakdown = [
            'assessed_at' => now()->toIso8601String(),
            'eligible' => $eligible,
            'blended_score' => $score,
            'threshold' => self::RECOMMENDATION_THRESHOLD,
            'recommendation' => $this->recommendation($eligible, $score, $flagged),
            'flagged_for_review' => $flagged,
            'strategies' => array_map(fn (EligibilityResult $r) => $r->toArray(), $results),
        ];

        $application->forceFill([
            'eligibility_score' => $score,
            'eligibility_breakdown' => $breakdown,
            'agency_verification' => $verification,
            'verified_at' => now(),
        ])->save();

        $this->auditLogger->log('application.assessed', $application, [
            'score' => $score,
            'eligible' => $eligible,
            'recommendation' => $breakdown['recommendation'],
        ]);

        return $breakdown;
    }

    /**
     * Weighted mean of the applicable strategy scores.
     *
     * A weighted mean rather than a sum keeps the result on the 0-100 scale however
     * many strategies happen to apply.
     */
    private function blend(array $results): int
    {
        if ($results === []) {
            return 0;
        }

        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($results as $index => $result) {
            $weight = $this->weightFor($result->strategy);
            $weighted += $result->score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? (int) round($weighted / $totalWeight) : 0;
    }

    private function weightFor(string $strategyName): float
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->name() === $strategyName) {
                return $strategy->weight();
            }
        }

        return 1.0;
    }

    private function recommendation(bool $eligible, int $score, bool $flagged): string
    {
        if (! $eligible) {
            return 'reject';
        }

        if ($flagged) {
            return 'manual_review';
        }

        return $score >= self::RECOMMENDATION_THRESHOLD ? 'approve' : 'manual_review';
    }

    /** Delegates to Module 1's factory so document rules stay defined in one place. */
    public function requiredDocumentsFor(Application $application): array
    {
        return $this->programService->requiredDocumentsFor($application->aidProgram);
    }
}
