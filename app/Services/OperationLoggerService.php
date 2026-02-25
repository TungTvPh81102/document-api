<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

/**
 * Operation Logger Service
 * 
 * Ghi log các truy vấn database, cache operations, external API calls
 * Theo dõi hiệu năng và phát hiện bottlenecks
 */
class OperationLoggerService
{
    private const DB_LOG_CHANNEL = 'database';
    private const PERFORMANCE_CHANNEL = 'performance';
    private const TABLE_NAME = 'operation_logs';

    private bool $useQueue;
    private bool $useDatabase;
    private int $slowQueryThreshold; // milliseconds
    private int $slowCacheThreshold; // milliseconds

    public function __construct()
    {
        $this->useQueue = config('logging.operation_logger.use_queue', true);
        $this->useDatabase = config('logging.operation_logger.use_database', true);
        $this->slowQueryThreshold = config('logging.operation_logger.slow_query_threshold', 1000);
        $this->slowCacheThreshold = config('logging.operation_logger.slow_cache_threshold', 100);
    }

    /**
     * Log database query
     * Logs to both database and file
     */
    public function logQuery(
        string $sql,
        array $bindings,
        float $durationMs,
        ?string $connection = null
    ): void {
        try {
            $isSlow = $durationMs > $this->slowQueryThreshold;
            $level = $isSlow ? 'warning' : 'debug';

            $data = [
                'id'            => Str::orderedUuid(),
                'operation'     => 'query',
                'type'          => $this->detectQueryType($sql),
                'duration_ms'   => round($durationMs, 2),
                'is_slow'       => $isSlow,
                'query'         => Str::limit($sql, 1000),
                'bindings'      => json_encode($bindings, JSON_UNESCAPED_UNICODE),
                'connection'    => $connection ?? config('database.default'),
                'user_id'       => auth()->id(),
                'timestamp'     => now(),
            ];

            // Log to file first
            if ($isSlow) {
                $this->logToFile(
                    "Slow Query ({$durationMs}ms): {$this->detectQueryType($sql)}",
                    $data,
                    $level
                );
            }

            // Then to database
            if ($this->useDatabase && $isSlow) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogOperationJob::dispatch($data)->onQueue('logs');
                    } else {
                        $this->insertOperationLog($data);
                    }
                } catch (Exception $dbError) {
                    $this->logToFile(
                        "Failed to log slow query to database",
                        ['error' => $dbError->getMessage()],
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logToFile(
                'Failed to log query',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Log database operation (insert, update, delete)
     * Logs to both database and file
     */
    public function logDatabaseOperation(
        string $operation,
        string $table,
        ?string $id = null,
        float $durationMs = 0,
        ?int $rowsAffected = null
    ): void {
        try {
            $data = [
                'id'            => Str::orderedUuid(),
                'operation'     => $operation, // 'insert', 'update', 'delete', 'truncate'
                'type'          => 'database_op',
                'table'         => $table,
                'target_id'     => $id,
                'duration_ms'   => round($durationMs, 2),
                'rows_affected' => $rowsAffected,
                'user_id'       => auth()->id(),
                'timestamp'     => now(),
            ];

            // Log to file
            $this->logToFile(
                "DB Operation: {$operation} on {$table}",
                $data,
                'info'
            );

            // Then DB
            if ($this->useDatabase) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogOperationJob::dispatch($data)->onQueue('logs');
                    } else {
                        $this->insertOperationLog($data);
                    }
                } catch (Exception $dbError) {
                    $this->logToFile(
                        "Failed to log database operation to database",
                        ['error' => $dbError->getMessage(), 'data' => $data],
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logToFile(
                'Failed to log database operation',
                [
                    'error' => $e->getMessage(),
                    'operation' => $operation,
                    'trace' => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Log cache operation (hit, miss, set, get, forget)
     * Logs to both database and file
     */
    public function logCacheOperation(
        string $operation,
        string $key,
        float $durationMs,
        ?bool $hit = null,
        ?string $value = null
    ): void {
        try {
            $isSlow = $durationMs > $this->slowCacheThreshold;

            $data = [
                'id'            => Str::orderedUuid(),
                'operation'     => $operation, // 'get', 'put', 'remove', 'hit', 'miss'
                'type'          => 'cache_op',
                'cache_key'     => Str::limit($key, 255),
                'duration_ms'   => round($durationMs, 2),
                'is_slow'       => $isSlow,
                'hit'           => $hit,
                'value_size'    => $value ? strlen($value) : 0,
                'user_id'       => auth()->id(),
                'timestamp'     => now(),
            ];

            // Log to file if slow
            if ($isSlow) {
                $this->logToFile(
                    "Slow Cache Operation: {$operation} (key: {$key}, {$durationMs}ms)",
                    $data,
                    'info'
                );
            }

            // Then DB if slow and database enabled
            if ($this->useDatabase && $isSlow) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogOperationJob::dispatch($data)->onQueue('logs');
                    } else {
                        $this->insertOperationLog($data);
                    }
                } catch (Exception $dbError) {
                    $this->logToFile(
                        "Failed to log cache operation to database",
                        ['error' => $dbError->getMessage(), 'data' => $data],
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logToFile(
                'Failed to log cache operation',
                [
                    'error' => $e->getMessage(),
                    'operation' => $operation,
                    'trace' => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Log external API call
     * Logs to both database and file
     */
    public function logApiCall(
        string $method,
        string $url,
        float $durationMs,
        int $statusCode,
        ?string $errorMessage = null
    ): void {
        try {
            $isError = $statusCode >= 400;
            $isSlow = $durationMs > 5000; // 5 seconds for external APIs

            $data = [
                'id'            => Str::orderedUuid(),
                'operation'     => 'api_call',
                'type'          => 'external_api',
                'method'        => $method,
                'url'           => Str::limit($url, 500),
                'status_code'   => $statusCode,
                'duration_ms'   => round($durationMs, 2),
                'is_error'      => $isError,
                'is_slow'       => $isSlow,
                'error'         => $errorMessage,
                'user_id'       => auth()->id(),
                'timestamp'     => now(),
            ];

            // Log to file if error or slow
            if ($isError || $isSlow) {
                $level = $isError ? 'warning' : 'info';
                $this->logToFile(
                    "API Call: {$method} {$url} ({$statusCode}, {$durationMs}ms)",
                    $data,
                    $level
                );
            }

            // Then DB if error or slow and database enabled
            if ($this->useDatabase && ($isError || $isSlow)) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogOperationJob::dispatch($data)->onQueue('logs');
                    } else {
                        $this->insertOperationLog($data);
                    }
                } catch (Exception $dbError) {
                    $this->logToFile(
                        "Failed to log API call to database",
                        ['error' => $dbError->getMessage(), 'data' => $data],
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logToFile(
                'Failed to log API call',
                [
                    'error' => $e->getMessage(),
                    'method' => $method,
                    'url' => $url,
                    'trace' => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Get query statistics
     */
    public function getQueryStats(?string $table = null, ?int $days = 7): array
    {
        try {
            $query = DB::table(self::TABLE_NAME)
                ->where('type', 'query')
                ->where('timestamp', '>=', now()->subDays($days));

            if ($table) {
                $query->where('query', 'like', "%{$table}%");
            }

            return [
                'total_queries'      => $query->count(),
                'avg_duration_ms'    => round($query->avg('duration_ms'), 2),
                'max_duration_ms'    => $query->max('duration_ms'),
                'slow_queries_count' => $query->where('is_slow', true)->count(),
                'slow_queries_pct'   => round(
                    ($query->clone()->where('is_slow', true)->count() / max($query->count(), 1)) * 100,
                    2
                ),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get query stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get slow operations by type
     */
    public function getSlowOperations(?string $type = null, int $limit = 50): array
    {
        try {
            $query = DB::table(self::TABLE_NAME)
                ->where('is_slow', true)
                ->orderBy('timestamp', 'desc')
                ->limit($limit);

            if ($type) {
                $query->where('type', $type);
            }

            return $query->get()->toArray();
        } catch (Exception $e) {
            Log::error('Failed to get slow operations', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Private - Detect query type
     */
    private function detectQueryType(string $sql): string
    {
        $sql = strtoupper(trim($sql));

        if (str_starts_with($sql, 'SELECT')) return 'select';
        if (str_starts_with($sql, 'INSERT')) return 'insert';
        if (str_starts_with($sql, 'UPDATE')) return 'update';
        if (str_starts_with($sql, 'DELETE')) return 'delete';
        if (str_starts_with($sql, 'CREATE')) return 'create';
        if (str_starts_with($sql, 'ALTER')) return 'alter';
        if (str_starts_with($sql, 'DROP')) return 'drop';
        if (str_starts_with($sql, 'TRUNCATE')) return 'truncate';

        return 'other';
    }

    /**
     * Private - Insert operation log
     * Silently fails to file logging if DB insertion fails
     */
    private function insertOperationLog(array $data): void
    {
        try {
            DB::table(self::TABLE_NAME)->insert($data);
        } catch (Exception $e) {
            // Silent failure - file logging already handled in calling method
        }
    }

    /**
     * Private - Log to file with fallback
     * Ensures logging never completely fails
     */
    private function logToFile(string $message, array $context, string $level = 'info'): void
    {
        try {
            Log::channel(self::PERFORMANCE_CHANNEL)->{$level}($message, $context);
        } catch (Exception $e) {
            // Fallback to error_log if channel fails
            @error_log("{$level}: {$message} - " . json_encode($context));
        }
    }
}
