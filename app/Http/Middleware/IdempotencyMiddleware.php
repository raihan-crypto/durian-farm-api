<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    use ApiResponse;

    /**
     * Default TTL for cached idempotent responses (in seconds).
     */
    public const IDEMPOTENCY_TTL_SECONDS = 300;

    /**
     * Window in seconds for detecting rapid duplicate clicks (in seconds).
     */
    public const DEDUPLICATION_WINDOW_SECONDS = 2;

    /**
     * Handle an incoming request with idempotency protection and duplicate prevention.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Idempotency applies strictly to mutating requests
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $user = $request->user();
        $userId = $user?->id ?? $request->ip();

        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?? $request->header('Idempotency-Key');

        // Case 1: Explicit Idempotency Key provided (e.g. mobile sync, payments, critical forms)
        if ($idempotencyKey) {
            $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";
            $lockKey = "idempotency_lock:{$userId}:{$idempotencyKey}";

            // Check if already completed and cached
            $cached = Cache::get($cacheKey);
            if ($cached && is_array($cached)) {
                return response()->json($cached['data'], $cached['status'])
                    ->header('X-Idempotent-Replayed', 'true');
            }

            // Acquire atomic lock (10 seconds timeout) to prevent simultaneous duplicate execution
            $lock = Cache::lock($lockKey, 10);
            if (! $lock->get()) {
                return $this->errorResponse(
                    'Permintaan serupa sedang diproses oleh sistem. Harap tunggu sesaat.',
                    409
                );
            }

            try {
                $response = $next($request);

                if ($response instanceof JsonResponse && $response->isSuccessful()) {
                    Cache::put($cacheKey, [
                        'status' => $response->status(),
                        'data' => $response->getData(true),
                    ], self::IDEMPOTENCY_TTL_SECONDS);
                }

                return $response;
            } finally {
                $lock->release();
            }
        }

        // Case 2: Automatic rapid deduplication against accidental double-clicks (2s window)
        // Skip in testing environment unless explicitly requested to prevent test suite collision
        if (app()->environment('testing') && ! $request->hasHeader('X-Test-Dedup')) {
            return $next($request);
        }

        // Avoid serializing UploadedFile instances
        $inputs = $request->except(array_keys($request->allFiles()));
        $payloadHash = md5($request->method().'|'.$request->path().'|'.json_encode($inputs));
        $dedupKey = "rapid_dedup:{$userId}:{$payloadHash}";

        if (! Cache::add($dedupKey, 1, self::DEDUPLICATION_WINDOW_SECONDS)) {
            return $this->errorResponse(
                'Permintaan duplikat terdeteksi dalam waktu sangat singkat. Tindakan telah dicegah.',
                429
            );
        }

        return $next($request);
    }
}
