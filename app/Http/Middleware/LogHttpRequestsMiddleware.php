<?php

namespace App\Http\Middleware;

use App\Services\LoggerService;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class LogHttpRequestsMiddleware
{
    public function __construct(private LoggerService $logger) {}

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        try {
            $response = $next($request);

            // Log successful request with status and duration
            $duration = microtime(true) - $start;
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
            $this->logger->logFullRequestToSqlLog($request, $status, $duration, false, null);

            return $response;
        } catch (Throwable $e) {
            // Log failed request and rethrow for default handler
            $duration = microtime(true) - $start;
            $this->logger->logFullRequestToSqlLog($request, 500, $duration, true, $e->getMessage());
            throw $e;
        }
    }
}
