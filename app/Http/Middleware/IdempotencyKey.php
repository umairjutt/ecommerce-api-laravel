<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redis-backed idempotency for unsafe POSTs (e.g. checkout). A client sends a
 * unique `Idempotency-Key` header; the first request is processed and its
 * response cached, and any retry with the same key replays that response
 * instead of charging the customer twice.
 *
 * A short-lived lock guards against concurrent duplicates landing in the
 * window before the first response is stored.
 */
class IdempotencyKey
{
    private const TTL_SECONDS = 86400;
    private const LOCK_TTL_SECONDS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $scope = sprintf('idem:%s:%s', $request->user()?->id ?? 'guest', $key);
        $storeKey = "{$scope}:response";
        $lockKey = "{$scope}:lock";

        $cached = Redis::get($storeKey);
        if ($cached !== null) {
            return $this->replay($cached);
        }

        // SET NX: only the first caller acquires the lock.
        $acquired = Redis::set($lockKey, '1', 'EX', self::LOCK_TTL_SECONDS, 'NX');
        if (! $acquired) {
            return response()->json([
                'message' => 'A request with this Idempotency-Key is already in progress.',
            ], 409);
        }

        try {
            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                Redis::setex($storeKey, self::TTL_SECONDS, $this->serialize($response));
            }

            return $response;
        } finally {
            Redis::del($lockKey);
        }
    }

    private function serialize(Response $response): string
    {
        return json_encode([
            'status' => $response->getStatusCode(),
            'body' => $response->getContent(),
        ], JSON_THROW_ON_ERROR);
    }

    private function replay(string $cached): Response
    {
        $payload = json_decode($cached, true);

        return response($payload['body'] ?? '', $payload['status'] ?? 200)
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }
}
