<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clean up old logs
 * 
 * Xóa các log cũ theo chính sách retention
 * php artisan logs:cleanup
 */
class CleanupOldLogsCommand extends Command
{
    protected $signature = 'logs:cleanup {--force : Skip confirmation}';
    protected $description = 'Clean up old logs based on retention policy';

    public function handle()
    {
        $config = config('logging-enhanced.retention');

        if (!$config) {
            $this->error('Log retention configuration not found');
            return Command::FAILURE;
        }

        $deleted = 0;

        // Clean request logs
        $deleted += $this->cleanupTable(
            'cim_sql_log',
            $config['request_logs_days'] ?? 90,
            'Request logs'
        );

        // Clean action logs
        $deleted += $this->cleanupTable(
            'action_logs',
            $config['action_logs_days'] ?? 180,
            'Action logs'
        );

        // Clean auth logs
        $deleted += $this->cleanupTable(
            'auth_logs',
            $config['action_logs_days'] ?? 180,
            'Auth logs'
        );

        // Clean operation logs (slow queries)
        $deleted += $this->cleanupTable(
            'operation_logs',
            $config['operation_logs_days'] ?? 60,
            'Operation logs'
        );

        // Clean security events
        $deleted += $this->cleanupTable(
            'security_events',
            $config['security_logs_days'] ?? 365,
            'Security logs'
        );

        $this->info("✓ Successfully deleted {$deleted} old log records");

        return Command::SUCCESS;
    }

    private function cleanupTable(string $table, int $days, string $label): int
    {
        try {
            $cutoffDate = now()->subDays($days);

            $deleted = DB::table($table)
                ->where('created_at', '<', $cutoffDate)
                ->orWhere('timestamp', '<', $cutoffDate)
                ->delete();

            if ($deleted > 0) {
                $this->line("<fg=green>✓</> Deleted {$deleted} {$label} (older than {$days} days)");
            }

            return $deleted;
        } catch (\Exception $e) {
            $this->warn("  Could not clean {$table}: " . $e->getMessage());
            return 0;
        }
    }
}
