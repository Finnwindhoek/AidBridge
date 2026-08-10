<?php

namespace App\Http\Controllers;

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
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:60'],
            'reference_code' => ['required', 'string', 'max:40'],
            'bank_reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->service->handleWebhook(
            $data['idempotency_key'],
            $data['event_type'],
            $data['reference_code'],
            $request->only(['bank_reference', 'reason', 'amount', 'currency']),
        );

        // A duplicate is answered 200: the gateway has done nothing wrong and must
        // not keep retrying. A rejected transition is 422 so it surfaces in their
        // dashboard for investigation.
        $httpStatus = match ($result['status']) {
            'processed', 'duplicate' => 200,
            'rejected' => 422,
            default => 202,
        };

        return response()->json($result, $httpStatus);
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
