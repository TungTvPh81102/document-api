<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

/**
 * Business Action Logger
 * 
 * Ghi log mọi hành động kinh doanh (CRUD operations, trạng thái thay đổi, etc.)
 * Hỗ trợ async logging thông qua queue
 */
class ActionLoggerService
{
    private const LOG_CHANNEL = 'user_activity';
    private const TABLE_NAME = 'action_logs';
    private bool $useQueue;
    private bool $useDatabase;

    public function __construct()
    {
        $this->useQueue = config('logging.action_logger.use_queue', true);
        $this->useDatabase = config('logging.action_logger.use_database', true);
    }

    /**
     * Log hành động CRUD trên model
     * Logs to both database and file with proper error handling
     */
    public function logModelAction(
        Model $model,
        string $action, // 'create', 'update', 'delete', 'restore'
        ?array $changes = null,
        ?string $description = null
    ): void {
        try {
            $data = [
                'id'            => Str::orderedUuid(),
                'user_id'       => auth()->id(),
                'model_class'   => $model::class,
                'model_id'      => (string) $model->id,
                'model_name'    => class_basename($model),
                'action'        => $action,
                'changes'       => json_encode($changes, JSON_UNESCAPED_UNICODE),
                'description'   => $description,
                'ip_address'    => request()?->ip(),
                'user_agent'    => Str::limit(request()?->userAgent() ?? '', 255),
                'timestamp'     => now(),
            ];

            // Always log to file FIRST (more reliable)
            $this->logToFile(
                "Model Action: {$action} on {$data['model_name']}",
                $data,
                'info'
            );

            // Then log to database
            if ($this->useDatabase) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogActionJob::dispatch($data)->onQueue('logs');
                    } else {
                        $this->insertActionLog($data);
                    }
                } catch (Exception $dbError) {
                    // Even if DB fails, file log already succeeded
                    $this->logToFile(
                        "Database logging failed for model action",
                        ['error' => $dbError->getMessage(), 'original_data' => $data],
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            // Log to file as fallback
            $this->logToFile(
                'Failed to log model action',
                [
                    'error'   => $e->getMessage(),
                    'model'   => $model::class,
                    'action'  => $action,
                    'trace'   => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Log hành động hệ thống tùy chỉnh
     * Logs to both database and file
     */
    public function logAction(
        string $action,
        string $source, // controller, service, command, etc.
        ?string $description = null,
        ?array $metadata = null
    ): void {
        try {
            $data = [
                'id'            => Str::orderedUuid(),
                'user_id'       => auth()->id(),
                'model_class'   => null,
                'model_id'      => null,
                'model_name'    => null,
                'action'        => $action,
                'source'        => $source,
                'changes'       => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'description'   => $description,
                'ip_address'    => request()?->ip(),
                'user_agent'    => Str::limit(request()?->userAgent() ?? '', 255),
                'timestamp'     => now(),
            ];

            // Log to file first
            $this->logToFile(
                "Action: {$action} from {$source}",
                $data,
                'info'
            );

            // Then DB
            if ($this->useDatabase) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogActionJob::dispatch($data)->onQueue('logs');
                    } else {
                        $this->insertActionLog($data);
                    }
                } catch (Exception $dbError) {
                    $this->logToFile(
                        "Database logging failed for action",
                        ['error' => $dbError->getMessage(), 'data' => $data],
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logToFile(
                'Failed to log action',
                [
                    'error'   => $e->getMessage(),
                    'action'  => $action,
                    'trace'   => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Log xác thực (login, logout, lock account)
     * Logs to both database and file
     */
    public function logAuthAction(string $action, $user, ?string $reason = null): void
    {
        try {
            $data = [
                'id'            => Str::orderedUuid(),
                'user_id'       => $user->id ?? auth()->id(),
                'action'        => $action, // 'login', 'logout', 'failed_login', 'lock', 'unlock'  
                'description'   => $reason,
                'ip_address'    => request()?->ip(),
                'user_agent'    => Str::limit(request()?->userAgent() ?? '', 255),
                'timestamp'     => now(),
            ];

            // Log to file first
            $this->logToFile(
                "Auth Action: {$action} user={$data['user_id']}",
                $data,
                'info'
            );

            // Then DB
            if ($this->useDatabase) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogAuthActionJob::dispatch($data)->onQueue('logs');
                    } else {
                        DB::table('auth_logs')->insert($data);
                    }
                } catch (Exception $dbError) {
                    $this->logToFile(
                        "Database logging failed for auth action",
                        ['error' => $dbError->getMessage(), 'data' => $data],
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logToFile(
                'Failed to log auth action',
                [
                    'error'   => $e->getMessage(),
                    'action'  => $action,
                    'trace'   => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Log lỗi bảo mật
     * Logs to both database and file with proper error handling
     */
    public function logSecurityEvent(
        string $eventType, // 'suspicious_access', 'permission_denied', 'rate_limit', etc
        ?string $description = null,
        ?array $context = null
    ): void {
        try {
            $data = [
                'id'            => Str::orderedUuid(),
                'user_id'       => auth()->id(),
                'event_type'    => $eventType,
                'description'   => $description,
                'context'       => json_encode($context, JSON_UNESCAPED_UNICODE),
                'ip_address'    => request()?->ip(),
                'user_agent'    => Str::limit(request()?->userAgent() ?? '', 255),
                'timestamp'     => now(),
            ];

            // Log to security channel first (important!)
            $this->logToFile(
                "Security Event: {$eventType}",
                $data,
                'warning'
            );

            // Then DB
            if ($this->useDatabase) {
                try {
                    if ($this->useQueue) {
                        \App\Jobs\LogSecurityEventJob::dispatch($data)->onQueue('logs');
                    } else {
                        DB::table('security_events')->insert($data);
                    }
                } catch (Exception $dbError) {
                    $this->logToFile(
                        "Database logging failed for security event",
                        ['error' => $dbError->getMessage(), 'data' => $data],
                        'error'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logToFile(
                'Failed to log security event',
                [
                    'error'   => $e->getMessage(),
                    'eventType' => $eventType,
                    'trace'   => $e->getTraceAsString(),
                ],
                'error'
            );
        }
    }

    /**
     * Private - Insert action log directly to database
     */
    private function insertActionLog(array $data): void
    {
        try {
            DB::table(self::TABLE_NAME)->insert($data);
        } catch (Exception $e) {
            // Silently fail and log to file
            $this->logToFile(
                'Failed to insert action log to database',
                ['error' => $e->getMessage()],
                'error'
            );
        }
    }

    /**
     * Private - Log to file channel with structured format
     */
    private function logToFile(string $message, array $context, string $level = 'info'): void
    {
        try {
            $channel = Log::channel(self::LOG_CHANNEL);

            $level = strtolower($level);
            if (method_exists($channel, $level)) {
                $channel->{$level}($message, $context);
            } else {
                $channel->info($message, $context);
            }
        } catch (Exception $e) {
            // If file logging fails, at least try to log to default channel
            try {
                error_log("[{$level}] {$message}: " . json_encode($context));
            } catch (Exception) {
                // Silent fail
            }
        }
    }
}
