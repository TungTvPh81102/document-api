<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Archive old logs to storage
 * 
 * Chuyển các log cũ sang lưu trữ để giảm kích thước database
 * php artisan logs:archive
 */
class ArchiveOldLogsCommand extends Command
{
    protected $signature = 'logs:archive {--days=90 : Archive logs older than this many days}';
    protected $description = 'Archive old logs to storage and delete from database';

    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Archiving logs older than {$days} days (before {$cutoffDate})...");

        $archived = 0;
        $archived += $this->archiveTable('cim_sql_log', $cutoffDate, 'request_logs');
        $archived += $this->archiveTable('action_logs', $cutoffDate, 'action_logs');
        $archived += $this->archiveTable('auth_logs', $cutoffDate, 'auth_logs');
        $archived += $this->archiveTable('operation_logs', $cutoffDate, 'operation_logs');

        $this->info("✓ Successfully archived {$archived} log records");

        return Command::SUCCESS;
    }

    private function archiveTable(string $table, $cutoffDate, string $storagePath): int
    {
        try {
            $count = DB::table($table)
                ->where('created_at', '<', $cutoffDate)
                ->count();

            if ($count === 0) {
                $this->comment("  No records to archive in {$table}");
                return 0;
            }

            // Get records in batches
            $batchSize = 10000;
            $deleted = 0;
            $allRecords = [];

            DB::table($table)
                ->where('created_at', '<', $cutoffDate)
                ->orderBy('created_at')
                ->chunk($batchSize, function ($records) use ($table, &$deleted, &$allRecords) {
                    $allRecords = array_merge($allRecords, $records->toArray());
                    $deleted += count($records);

                    // Delete batch
                    DB::table($table)
                        ->whereIn('id', collect($records)->pluck('id'))
                        ->delete();
                });

            // Save to JSON file in storage
            $filename = "{$storagePath}/" . now()->format('Y-m-d') . "_archive_{$table}.json";
            Storage::disk('local')->put($filename, json_encode($allRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->line("  <fg=green>✓</> Archived {$deleted} records from {$table} to storage/{$filename}");

            return $deleted;
        } catch (\Exception $e) {
            $this->error("  Failed to archive {$table}: " . $e->getMessage());
            return 0;
        }
    }
}
