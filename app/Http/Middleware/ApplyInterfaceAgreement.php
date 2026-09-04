<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace App\Http\Middleware;

use App\Support\RequestContext;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * INTERFACE AGREEMENT (IFA) enforcement for the REST API.
 *
 * Every JSON response leaving the API is wrapped in the agreed envelope:
 *
 *   {
 *     "status":    "S" | "F" | "E",
 *     "timeStamp": "YYYY-MM-DD HH:MM:SS",
 *     "requestID": "<uuid>",
 *     "data":      { ... the endpoint's own payload ... }
 *   }
 *
 * Applying this as middleware rather than editing every endpoint means a new
 * route cannot accidentally be published outside the agreement, and the
 * envelope is guaranteed to be identical across the whole surface.
 *
 * The requestID is taken from the caller when supplied, so a consumer can
 * correlate its own logs with ours. When it is absent we fall back to the
 * correlation ID already minted for this request by the RequestContext
 * singleton, which is the same value stamped on every audit row — so an API
 * call can be traced from the consumer's log all the way into our audit trail.
 */
class ApplyInterfaceAgreement
{
    /** Header a consumer may use to supply its own tracking identifier. */
    public const REQUEST_ID_HEADER = 'X-Request-ID';

    /** Format mandated by the interface agreement for every timestamp. */
    public const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        // The agreement says this service answers in JSON. Without this, a consumer
        // that omits an Accept header is redirected to the HTML sign-in page on a
        // validation or authentication failure, so the same endpoint would answer
        // in two different formats depending on the outcome.
        $request->headers->set('Accept', 'application/json');

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // Render the exception here so that failures are returned inside the
            // agreed envelope too. Without this, a validation or authorisation
            // failure would unwind past this middleware and leave the API
            // answering in two different shapes depending on the outcome.
            $handler = app(ExceptionHandler::class);
            $handler->report($e);
            $response = $handler->render($request, $e);
        }

        // Anything that is not JSON (a file download, a redirect) is outside the
        // agreement and is passed through untouched.
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $response->setData([
            'status' => $this->statusFor($response),
            'timeStamp' => now()->format(self::TIMESTAMP_FORMAT),
            'requestID' => $requestId,
            'data' => $response->getData(true),
        ]);

        $response->headers->set(self::REQUEST_ID_HEADER, $requestId);

        return $response;
    }

    /**
     * S = the request succeeded.
     * F = the request failed for a reason the caller can correct (validation,
     *     authorisation, not found).
     * E = the service itself errored and the caller can do nothing about it.
     */
    private function statusFor(JsonResponse $response): string
    {
        return match (true) {
            $response->isSuccessful() => 'S',
            $response->isServerError() => 'E',
            default => 'F',
        };
    }

    /** Caller-supplied identifier if present, otherwise this request's correlation ID. */
    private function resolveRequestId(Request $request): string
    {
        $supplied = $request->header(self::REQUEST_ID_HEADER)
            ?? $request->input('requestID');

        $supplied = is_string($supplied) ? trim($supplied) : '';

        // Bounded and character-restricted: the value is echoed back to the
        // caller and written to logs, so it is never accepted unchecked.
        if ($supplied !== '' && preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $supplied)) {
            return $supplied;
        }

        return RequestContext::getInstance()->correlationId();
    }
}
