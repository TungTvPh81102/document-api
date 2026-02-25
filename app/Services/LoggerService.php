<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\SecurityUtil;
use Throwable;

/**
 * Unified Grafana/Loki-compatible logger.
 *
 * Every API request is logged ONCE via middleware to:
 *   1. Database (cim_sql_log) – for queryable audit trail
 *   2. Structured JSON file  – for Promtail/Loki/Grafana ingestion
 */
class LoggerService
{
    private const SERVICE_NAME = 'document-api';

    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'credit_card',
        'cvv',
        'pin',
    ];

    private bool $useQueue;
    private bool $disableLoggingForPaths;

    public function __construct()
    {
        $this->useQueue = config('logging.request_logger.use_queue', false);
        $this->disableLoggingForPaths = config('logging.request_logger.skip_paths_enabled', true);
    }

    /**
     * Log a complete HTTP request/response cycle.
     * Called by LogHttpRequestsMiddleware after the response is ready.
     */
    public function logRequest(
        Request   $request,
        mixed     $response,
        string    $startTime,
        string    $endTime,
        float     $durationMs,
        bool      $isError = false,
        ?string   $failResult = null,
    ): void {
        // Skip logging for certain paths
        if ($this->shouldSkipLogging($request)) {
            return;
        }

        try {
            $traceId      = $request->header('X-Request-ID', (string) Str::orderedUuid());
            $statusCode   = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 500;
            $level        = $this->resolveLevel($statusCode, $isError);
            $action       = $this->resolveAction($request);
            $functionName = $action['function'];
            $logicName    = $action['controller'];

            // ── Build the structured payload ──
            $logData = [
                'timestamp'        => $endTime,
                'level'            => $level,
                'service'          => self::SERVICE_NAME,
                'trace_id'         => $traceId,
                'method'           => $request->getMethod(),
                'route'            => $request->getPathInfo(),
                'status_code'      => $statusCode,
                'duration_ms'      => round($durationMs, 2),
                'function_name'    => $functionName,
                'logic_name'       => $logicName,
                'user_id'          => auth()->id() ?? 'guest',
                'ip'               => $request->ip(),
                'user_agent'       => Str::limit($request->userAgent(), 255),
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'parameters'       => $this->sanitize($this->extractParameters($request)),
                'response_summary' => $this->extractResponseSummary($response),
            ];

            if ($isError && $failResult) {
                $logData['fail_result'] = $failResult;
            }

            // ── 1. Write structured JSON to file (for Promtail/Loki) ──
            $this->writeToFile($logData, $level);

            // ── 2. Write to database (cim_sql_log) ──
            if ($this->useQueue) {
                // Dispatch to queue for async processing
                \App\Jobs\LogRequestJob::dispatch($logData, $traceId)->onQueue('logs');
            } else {
                $this->writeToDatabase($logData, $traceId);
            }
        } catch (Throwable $e) {
            // Fallback: never let logging break the app
            Log::error('[LoggerService] Failed to log request', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
     *  Private helpers
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Write structured log to the grafana file channel.
     * Has fallback to error_log if channel fails
     */
    private function writeToFile(array $data, string $level): void
    {
        try {
            $channel = Log::channel('grafana');
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            match ($level) {
                'error', 'critical' => $channel->error($jsonData),
                'warning'           => $channel->warning($jsonData),
                default             => $channel->info($jsonData),
            };
        } catch (Throwable $e) {
            // Fallback to error_log if channel fails
            @error_log("[LoggerService] Failed to write to grafana channel: " . $e->getMessage());
        }
    }

    /**
     * Insert log record into cim_sql_log table.
     * Silently fails with error_log fallback
     */
    private function writeToDatabase(array $data, string $traceId): void
    {
        try {
            DB::table('cim_sql_log')->insert([
                'id'            => Str::orderedUuid(),
                'request_id'    => $traceId,
                'level'         => $data['level'],
                'service'       => $data['service'],
                'method'        => $data['method'],
                'url'           => Str::limit($data['route'], 500),
                'route'         => Str::limit($data['route'], 255),
                'status_code'   => $data['status_code'],
                'function_name' => $data['function_name'],
                'logic_name'    => $data['logic_name'],
                'parameters'    => json_encode($data['parameters'], JSON_UNESCAPED_UNICODE),
                'response_data' => Str::limit($data['response_summary'] ?? '', 2000),
                'fail_result'   => isset($data['fail_result']) ? Str::limit($data['fail_result'], 4000) : null,
                'start_time'    => $data['start_time'],
                'end_time'      => $data['end_time'],
                'duration_ms'   => $data['duration_ms'],
                'user_id'       => $data['user_id'],
                'ip_address'    => $data['ip'],
                'user_agent'    => $data['user_agent'],
                'is_error'      => in_array($data['level'], ['error', 'critical']),
                'message'       => Str::limit($data['response_summary'] ?? 'OK', 500),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (Throwable $e) {
            // Silent failure - file logging already succeeded in writeToFile()
            // Log to PHP error_log as ultimate fallback
            @error_log("[LoggerService] Failed to write to cim_sql_log: " . $e->getMessage());
        }
    }

    /**
     * Determine log level from HTTP status code.
     */
    private function resolveLevel(int $statusCode, bool $isError): string
    {
        if ($isError || $statusCode >= 500) return 'error';
        if ($statusCode >= 400)             return 'warning';
        return 'info';
    }

    /**
     * Resolve the controller class + method from the current route.
     */
    private function resolveAction(Request $request): array
    {
        $action = $request->route()?->getActionName() ?? 'Closure';

        if (str_contains($action, '@')) {
            [$controller, $function] = explode('@', $action);
            return [
                'controller' => class_basename($controller),
                'function'   => $function,
            ];
        }

        return [
            'controller' => 'Closure',
            'function'   => $action,
        ];
    }

    /**
     * Extract request parameters (body + query) for logging.
     */
    private function extractParameters(Request $request): array
    {
        return [
            'query' => $request->query() ?: [],
            'body'  => $request->all() ?: [],
        ];
    }

    /**
     * Extract a short summary from the response body.
     */
    private function extractResponseSummary(mixed $response): string
    {
        try {
            if (!method_exists($response, 'getContent')) {
                return 'no-content';
            }

            $content = $response->getContent();
            $decoded = json_decode($content, true);

            if (is_array($decoded)) {
                return $decoded['message'] ?? Str::limit($content, 200);
            }

            return Str::limit($content, 200);
        } catch (Throwable) {
            return 'unreadable';
        }
    }

    /**
     * Recursively redact sensitive fields.
     */
    private function sanitize(array $data): array
    {
        return SecurityUtil::redact($data);
    }

    /**
     * Check if logging should be skipped for this path
     */
    private function shouldSkipLogging(Request $request): bool
    {
        if (!$this->disableLoggingForPaths) {
            return false;
        }

        $skipPaths = config('logging.request_logger.skip_paths', [
            '/up',
            '/health',
            '/health/check',
            '/api/health',
            '/telescope',
            '*.js',
            '*.css',
            '*.png',
            '*.jpg',
            '*.svg',
            '/api/v1/docs',
        ]);

        $path = $request->getPathInfo();

        foreach ($skipPaths as $skipPath) {
            // Handle wildcard patterns
            if ($skipPath === '*' || $skipPath === '/*') {
                return true;
            }

            if (str_contains($skipPath, '*')) {
                $pattern = str_replace('*', '.*', preg_quote($skipPath, '#'));
                if (preg_match("#^{$pattern}$#", $path)) {
                    return true;
                }
            }

            if ($path === $skipPath) {
                return true;
            }
        }

        return false;
    }
}
