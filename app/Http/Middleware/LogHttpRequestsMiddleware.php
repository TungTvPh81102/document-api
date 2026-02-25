<?php

namespace App\Http\Middleware;

use App\Services\LoggerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * HTTP Request Logging Middleware
 * 
 * Ghi log mọi HTTP request/response với:
 * - Async processing (tuỳ chọn thông qua queue)
 * - Smart filtering (bỏ qua health checks, static files)
 * - Performance tracking
 * - Error detection
 * - Request tracing (correlation ID)
 */
class LogHttpRequestsMiddleware
{
    public function __construct(private LoggerService $logger) {}

    public function handle(Request $request, Closure $next)
    {
        // Add request ID header for tracing
        if (!$request->header('X-Request-ID')) {
            $request->headers->set('X-Request-ID', (string) \Illuminate\Support\Str::orderedUuid());
        }

        $startTime = Carbon::now();
        $start     = microtime(true);

        try {
            $response = $next($request);

            $endTime    = Carbon::now();
            $durationMs = (microtime(true) - $start) * 1000;
            $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

            $this->logger->logRequest(
                request: $request,
                response: $response,
                startTime: $startTime->toIso8601String(),
                endTime: $endTime->toIso8601String(),
                durationMs: $durationMs,
                isError: $statusCode >= 500,
            );

            return $response;
        } catch (Throwable $e) {
            $endTime    = Carbon::now();
            $durationMs = (microtime(true) - $start) * 1000;

            // Build a minimal error response for logging purposes
            $errorResponse = response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);

            $this->logger->logRequest(
                request: $request,
                response: $errorResponse,
                startTime: $startTime->toIso8601String(),
                endTime: $endTime->toIso8601String(),
                durationMs: $durationMs,
                isError: true,
                failResult: $e->getMessage() . "\n" . $e->getTraceAsString(),
            );

            throw $e;
        }
    }
}
