<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace App\Services\Integration;

use App\Http\Middleware\ApplyInterfaceAgreement;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for MODULE-TO-MODULE web service consumption.
 *
 * Each module consumes a sibling module's REST endpoint over HTTP rather than
 * calling its classes directly. The HTTP boundary is what makes these genuinely
 * web services: the modules could be deployed as separate applications tomorrow
 * without any consumer changing.
 *
 * Every subclass gets, for free:
 *
 *  - the Interface Agreement's mandatory request fields (requestID, timeStamp),
 *    with requestID taken from the RequestContext singleton so a call can be
 *    traced from the consumer's audit rows into the provider's;
 *  - envelope unwrapping, so callers receive the payload rather than the wrapper;
 *  - a timeout and retry, and safe degradation: a failed call returns a failure
 *    result rather than throwing, because one module being unreachable must not
 *    take another module's screen down;
 *  - a short-lived, least-privilege bearer token that is revoked immediately
 *    after the call, so no long-lived machine credential is stored anywhere.
 */
abstract class InternalServiceClient
{
    protected const TIMEOUT_SECONDS = 5;
    protected const RETRY_ATTEMPTS = 2;
    protected const RETRY_DELAY_MS = 200;

    /** Which module this client belongs to. */
    abstract protected function sourceModule(): string;

    /** Which module it calls. */
    abstract protected function targetModule(): string;

    /** The agreed function name, as published in the Interface Agreement. */
    abstract protected function functionName(): string;

    protected function baseUrl(): string
    {
        return rtrim((string) config('aidbridge.internal_api_base'), '/');
    }

    /**
     * Performs the call and returns a result object. Never throws: a transport
     * failure is reported, not propagated.
     */
    protected function call(User $actor, string $path, array $query = []): IntegrationResult
    {
        $requestId = RequestContext::getInstance()->correlationId();
        $url = $this->baseUrl().$path;

        $context = [
            'source' => $this->sourceModule(),
            'target' => $this->targetModule(),
            'function' => $this->functionName(),
            'url' => $url,
            'requestId' => $requestId,
        ];

        // Least privilege: the token carries only the abilities the actor already
        // holds, expires in two minutes, and is deleted in the finally block.
        $abilities = $actor->isAdmin() ? ['admin', 'beneficiary'] : ['beneficiary'];
        $token = $actor->createToken('module-integration', $abilities, now()->addMinutes(2));

        $startedAt = microtime(true);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, throw: false)
                ->withToken($token->plainTextToken)
                ->withHeaders([ApplyInterfaceAgreement::REQUEST_ID_HEADER => $requestId])
                ->acceptJson()
                ->get($url, $query + [
                    'requestID' => $requestId,
                    'timeStamp' => now()->format(ApplyInterfaceAgreement::TIMESTAMP_FORMAT),
                ]);

            $elapsed = round((microtime(true) - $startedAt) * 1000, 1);
            $body = $response->json();

            if (! is_array($body) || ! isset($body['status'])) {
                return IntegrationResult::failure(
                    $context, 'E', 'Response did not follow the interface agreement.',
                    $response->status(), $elapsed
                );
            }

            if ($body['status'] !== 'S') {
                return IntegrationResult::failure(
                    $context,
                    $body['status'],
                    $body['data']['message'] ?? 'The provider reported a failure.',
                    $response->status(),
                    $elapsed
                );
            }

            return IntegrationResult::success(
                $context,
                is_array($body['data'] ?? null) ? $body['data'] : ['value' => $body['data'] ?? null],
                (string) ($body['timeStamp'] ?? ''),
                $response->status(),
                $elapsed
            );
        } catch (Throwable $e) {
            Log::warning('AidBridge: module integration call failed.', [
                'source' => $context['source'],
                'target' => $context['target'],
                'function' => $context['function'],
                'error' => $e->getMessage(),
            ]);

            return IntegrationResult::failure(
                $context, 'E', $e->getMessage(), null,
                round((microtime(true) - $startedAt) * 1000, 1)
            );
        } finally {
            // The credential exists only for the duration of this one call.
            $token->accessToken->delete();
        }
    }
}
