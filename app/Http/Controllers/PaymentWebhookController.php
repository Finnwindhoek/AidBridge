<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 4 — Fund Allocation & Disbursement Tracking
 * Author: Kartik
 */

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyInterfaceAgreement;
use App\Services\Disbursement\DisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WEB SERVICE PROVIDER — Module 4.
 *
 * Receives payment-provider callbacks. This endpoint is unauthenticated in the
 * session sense and CSRF-exempt, so it defends itself with an HMAC signature and
 * an idempotency key.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly DisbursementService $service) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return $this->respond(['status' => 'rejected', 'message' => 'Invalid signature.'], 401, $request);
        }

        $data = $request->validate([
            // The idempotency key IS this integration's unique request identifier,
            // which is what the Interface Agreement requires for request tracking.
            'idempotency_key' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:60'],
            'reference_code' => ['required', 'string', 'max:40'],
            'bank_reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            // Optional because the idempotency key already satisfies the agreement's
            // "timestamp OR requestID" rule; accepted and logged when the gateway
            // sends it, so a delayed delivery can be identified.
            'timeStamp' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ]);

        $result = $this->service->handleWebhook(
            $data['idempotency_key'],
            $data['event_type'],
            $data['reference_code'],
            $request->only(['bank_reference', 'reason', 'amount', 'currency', 'timeStamp']),
        );

        // A duplicate is answered 200: the gateway has done nothing wrong and must
        // not keep retrying. A rejected transition is 422 so it surfaces in their
        // dashboard for investigation.
        $httpStatus = match ($result['status']) {
            'processed', 'duplicate' => 200,
            'rejected' => 422,
            default => 202,
        };

        return $this->respond($result, $httpStatus, $request);
    }

    /**
     * Adds the Interface Agreement's mandatory response fields to the gateway's
     * payload. The existing `status` key already reports the result of the
     * request, so it is kept rather than nested: this endpoint answers an
     * external party whose client we do not control, and re-shaping its response
     * body would be a breaking change to their integration.
     */
    private function respond(array $payload, int $httpStatus, Request $request): JsonResponse
    {
        return response()->json(array_merge($payload, [
            'timeStamp' => now()->format(ApplyInterfaceAgreement::TIMESTAMP_FORMAT),
            'requestID' => $request->input('idempotency_key'),
        ]), $httpStatus);
    }

    /**
     * Verifies the HMAC-SHA256 of the raw request body against the shared secret.
     * hash_equals is used so the comparison cannot be timing-attacked.
     */
    private function signatureIsValid(Request $request): bool
    {
        $secret = config('services.payment_gateway.webhook_secret');

        if (blank($secret)) {
            // Fail closed: without a configured secret no callback can be trusted.
            return false;
        }

        $provided = (string) $request->header('X-AidBridge-Signature');

        if ($provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }
}
