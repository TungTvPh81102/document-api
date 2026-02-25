<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Log Analytics Command
 * 
 * Phân tích logs và tạo báo cáo
 * php artisan logs:analytics {--days=7}
 */
class LogsAnalyticsCommand extends Command
{
    protected $signature = 'logs:analytics {--days=7 : Analyze logs from last N days}';
    protected $description = 'Analyze logs and generate reports';

    public function handle()
    {
        $days = $this->option('days');
        $since = now()->subDays($days);

        $this->info("=== LOG ANALYTICS (Last {$days} days) ===\n");

        // Request statistics
        $this->requestStats($since);
        $this->line('');

        // Error statistics
        $this->errorStats($since);
        $this->line('');

        // Performance statistics
        $this->performanceStats($since);
        $this->line('');

        // User activity statistics
        $this->userActivityStats($since);
        $this->line('');

        // Top slow queries
        $this->topSlowQueries($since);
        $this->line('');

        // Security events
        $this->securityEventsStats($since);

        return Command::SUCCESS;
    }

    private function requestStats($since): void
    {
        $this->info('<fg=cyan>REQUEST STATISTICS:</> ');

        $total = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->count();

        $byMethod = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->selectRaw('method, COUNT(*) as count')
            ->groupBy('method')
            ->get();

        $byStatusCode = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->selectRaw('status_code, COUNT(*) as count')
            ->groupBy('status_code')
            ->orderBy('count', 'desc')
            ->get();

        $this->line("  Total requests: {$total}");
        $this->line('  By method:');
        foreach ($byMethod as $row) {
            $this->line("    {$row->method}: {$row->count}");
        }
        $this->line('  By status code:');
        foreach ($byStatusCode as $row) {
            $this->line("    {$row->status_code}: {$row->count}");
        }
    }

    private function errorStats($since): void
    {
        $this->info('<fg=red>ERROR STATISTICS:</>');

        $errors = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->where('is_error', true)
            ->count();

        $errorsByRoute = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->where('is_error', true)
            ->selectRaw('route, COUNT(*) as count')
            ->groupBy('route')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        $this->line("  Total errors: {$errors}");
        $this->line('  Top error routes:');
        foreach ($errorsByRoute as $row) {
            $this->line("    {$row->route}: {$row->count}");
        }
    }

    private function performanceStats($since): void
    {
        $this->info('<fg=yellow>PERFORMANCE STATISTICS:</>');

        $avgDuration = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->avg('duration_ms');

        $maxDuration = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->max('duration_ms');

        $slowQueries = DB::table('operation_logs')
            ->where('timestamp', '>=', $since)
            ->where('is_slow', true)
            ->count();

        $this->line("  Average request time: " . round($avgDuration, 2) . "ms");
        $this->line("  Max request time: " . round($maxDuration, 2) . "ms");
        $this->line("  Slow queries detected: {$slowQueries}");
    }

    private function userActivityStats($since): void
    {
        $this->info('<fg=green>USER ACTIVITY:</>');

        $activeUsers = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->distinct('user_id')
            ->count('user_id');

        $topUsers = DB::table('cim_sql_log')
            ->where('created_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        $this->line("  Active users: {$activeUsers}");
        $this->line('  Top users:');
        foreach ($topUsers as $row) {
            $this->line("    User {$row->user_id}: {$row->count} requests");
        }
    }

    private function topSlowQueries($since): void
    {
        $this->info('<fg=magenta>TOP SLOW QUERIES:</>');

        $slowQueries = DB::table('operation_logs')
            ->where('timestamp', '>=', $since)
            ->where('is_slow', true)
            ->where('type', 'query')
            ->selectRaw('query, COUNT(*) as count, MAX(duration_ms) as max_duration')
            ->groupBy('query')
            ->orderBy('max_duration', 'desc')
            ->limit(5)
            ->get();

        $this->line('  Top 5 slowest queries:');
        foreach ($slowQueries as $row) {
            $query = substr($row->query, 0, 60) . (strlen($row->query) > 60 ? '...' : '');
            $this->line("    {$query} - {$row->max_duration}ms (called {$row->count}x)");
        }
    }

    private function securityEventsStats($since): void
    {
        $this->info('<fg=red>SECURITY EVENTS:</>');

        $events = DB::table('security_events')
            ->where('timestamp', '>=', $since)
            ->count();

        $byType = DB::table('security_events')
            ->where('timestamp', '>=', $since)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->get();

        $this->line("  Total security events: {$events}");
        $this->line('  By type:');
        foreach ($byType as $row) {
            $this->line("    {$row->event_type}: {$row->count}");
        }
    }
}
