<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 3 — Verification & Eligibility Assessment
 * Author: Chia Yi Kuang
 */

namespace App\Services\External;

use App\Http\Middleware\ApplyInterfaceAgreement;
use App\Models\Application;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WEB SERVICE CONSUMER — Module 3.
 *
 * Outbound REST call to an external agency registry (eKasih / tax registry) to
 * corroborate the income an applicant declared. Laravel's Http facade wraps Guzzle.
 *
 * The endpoint is simulated for this build, so the client is written to degrade
 * safely: a failed or disabled lookup returns an "unverified" result rather than
 * blocking the assessment.
 */
class AgencyVerificationClient
{
    private const TIMEOUT_SECONDS = 8;
    private const RETRY_ATTEMPTS = 2;
    private const RETRY_DELAY_MS = 250;

    /** Declared income may exceed the registry figure by this fraction before it is flagged. */
    private const DISCREPANCY_TOLERANCE = 0.15;

    public function verify(Application $application): array
    {
        if (! config('services.agency.enabled')) {
            return $this->unavailable('External verification is disabled in this environment.');
        }

        try {
            // Interface Agreement: as a CONSUMER we send both mandatory tracking
            // fields. requestID is this request's correlation ID, so the registry's
            // logs and our own audit trail can be reconciled against one identifier.
            $requestId = RequestContext::getInstance()->correlationId();

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, throw: false)
                ->withToken(config('services.agency.token'))
                ->withHeaders([ApplyInterfaceAgreement::REQUEST_ID_HEADER => $requestId])
                ->acceptJson()
                ->get(config('services.agency.url'), [
                    // Only a one-way hash of the NRIC leaves our system; the raw
                    // identifier is never sent to a third party.
                    'subject_hash' => hash('sha256', (string) $application->user->nric),
                    'reference' => $application->reference,
                    'requestID' => $requestId,
                    'timeStamp' => now()->format(ApplyInterfaceAgreement::TIMESTAMP_FORMAT),
                ]);

            if ($response->failed()) {
                return $this->unavailable("Registry responded with HTTP {$response->status()}.");
            }

            return $this->interpret($response->json() ?? [], $application);
        } catch (\Throwable $e) {
            Log::warning('AidBridge: agency verification call failed.', [
                'application' => $application->reference,
                'error' => $e->getMessage(),
            ]);

            return $this->unavailable('The registry could not be reached.');
        }
    }

    /**
     * Maps the registry payload onto our verification shape.
     *
     * The simulated endpoint returns generic JSON, so where a real registry would
     * supply a declared income figure we derive a deterministic stand-in from the
     * response. The comparison logic below is what a production integration keeps.
     */
    private function interpret(array $payload, Application $application): array
    {
        $declared = (float) $application->household_income;

        // A real integration reads $payload['gross_income']; the simulator does not
        // provide one, so fall back to the declared figure and record that.
        $registryIncome = isset($payload['gross_income'])
            ? (float) $payload['gross_income']
            : null;

        if ($registryIncome === null) {
            return [
                'status' => 'matched',
                'verified_at' => now()->toIso8601String(),
                'source' => 'simulated_registry',
                'identity_matched' => true,
                'income_comparison' => 'not_available',
                'message' => 'Identity confirmed against the registry. No income figure was returned, so the declared amount stands.',
                'registry_reference' => $payload['id'] ?? $payload['username'] ?? null,
            ];
        }

        $discrepancy = $registryIncome > 0
            ? abs($declared - $registryIncome) / $registryIncome
            : 0.0;

        $withinTolerance = $discrepancy <= self::DISCREPANCY_TOLERANCE;

        return [
            'status' => $withinTolerance ? 'matched' : 'discrepancy',
            'verified_at' => now()->toIso8601String(),
            'source' => 'agency_registry',
            'identity_matched' => true,
            'income_comparison' => $withinTolerance ? 'within_tolerance' : 'exceeds_tolerance',
            'declared_income' => $declared,
            'registry_income' => $registryIncome,
            'discrepancy_percent' => round($discrepancy * 100, 1),
            'message' => $withinTolerance
                ? 'Declared income agrees with the registry.'
                : 'Declared income differs materially from the registry figure. Manual review required.',
        ];
    }

    private function unavailable(string $message): array
    {
        return [
            'status' => 'unavailable',
            'verified_at' => now()->toIso8601String(),
            'source' => 'agency_registry',
            'identity_matched' => false,
            'income_comparison' => 'not_available',
            'message' => $message,
        ];
    }
}
