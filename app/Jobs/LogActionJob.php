<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queue job để ghi log hành động không đồng bộ
 */
class LogActionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private array $logData
    ) {
        $this->onQueue('logs');
    }

    public function handle(): void
    {
        try {
            DB::table('action_logs')->insert($this->logData);
        } catch (\Exception $e) {
            Log::error('Failed to log action in job', [
                'error'   => $e->getMessage(),
                'logData' => $this->logData,
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Log to file as ultimate fallback
        @error_log('LogActionJob failed permanently: ' . $exception->getMessage());

        // Also attempt to log to the configured logger
        Log::error('LogActionJob failed permanently', [
            'error'   => $exception->getMessage(),
            'logData' => $this->logData,
        ]);
    }
}
