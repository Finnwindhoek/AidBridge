<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 3 — Verification & Eligibility Assessment
 * Author: Chia Yi Kuang
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

/**
 * MODULE 3 — Verification & Eligibility Assessment: service exposure.
 *
 * Publishes the eligibility outcome of one application so that sibling modules
 * can read it without reaching into Module 3's tables. Module 4 consumes this
 * to size a payout; Module 5 consumes it for reporting.
 *
 * The response is wrapped in the Interface Agreement envelope by
 * ApplyInterfaceAgreement middleware, so this controller returns only the
 * payload.
 */
class EligibilityApiController extends Controller
{
    public function show(Request $request, Application $application): array
    {
        // Object-level authorisation, not merely route-level: a valid token for
        // one beneficiary must not read another beneficiary's assessment.
        abort_unless($request->user()->can('view', $application), 403);

        $breakdown = $application->eligibility_breakdown ?? [];

        return [
            'reference' => $application->reference,
            'assessed' => $application->verified_at !== null,
            'assessed_at' => $application->verified_at?->toIso8601String(),
            'eligible' => $breakdown['eligible'] ?? null,
            'blended_score' => $application->eligibility_score,
            'threshold' => $breakdown['threshold'] ?? null,
            'recommendation' => $breakdown['recommendation'] ?? null,
            'flagged_for_review' => $breakdown['flagged_for_review'] ?? false,
            // Each rule's own score and the reasons it recorded, so a consumer can
            // explain the outcome rather than only report the number.
            'strategies' => collect($breakdown['strategies'] ?? [])
                ->map(fn (array $s) => [
                    'strategy' => $s['strategy'] ?? null,
                    'score' => $s['score'] ?? null,
                    'eligible' => $s['eligible'] ?? null,
                    'reasons' => $s['reasons'] ?? [],
                ])->all(),
            'agency_verification' => [
                // The registry's raw payload is deliberately not republished; only
                // its outcome, so no third-party personal data leaves this module.
                'status' => $application->agency_verification['status'] ?? 'unavailable',
                'checked_at' => $application->agency_verification['checked_at'] ?? null,
            ],
        ];
    }
}
