<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LogRotate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:log-rotate {--days=30 : Number of days to keep logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate logs - delete logs older than specified days (default: 30 days / 1 month)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Starting log rotation...");
        $this->info("Deleting logs older than {$days} days (before {$cutoffDate->format('Y-m-d H:i:s')})");

        try {
            // Удаляем старые логи из таблицы log (максимум 1000 записей за раз)
            $totalDeleted = 0;
            $batchSize = 1000;

            do {
                $deletedCount = DB::delete("
                    DELETE FROM log
                    WHERE id IN (
                        SELECT id FROM (
                            SELECT id FROM log
                            WHERE created_at < ?
                            LIMIT ?
                        ) AS temp
                    )
                ", [$cutoffDate, $batchSize]);

                $totalDeleted += $deletedCount;

                if ($deletedCount > 0) {
                    $this->line("Deleted batch: {$deletedCount} entries (total: {$totalDeleted})");
                }

            } while ($deletedCount === $batchSize);

            $this->info("Successfully deleted {$totalDeleted} log entries from database.");

            // Удаляем старые файлы логов из storage/logs
            $logPath = storage_path('logs');
            $deletedFiles = 0;

            if (is_dir($logPath)) {
                $files = glob($logPath . '/*.log');

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $fileTime = filemtime($file);
                        if ($fileTime < $cutoffDate->timestamp) {
                            if (unlink($file)) {
                                $deletedFiles++;
                                $this->line("Deleted file: " . basename($file));
                            }
                        }
                    }
                }

                $this->info("Successfully deleted {$deletedFiles} old log files.");
            }

            Log::channel('app')->info("Log rotation completed", [
                'days' => $days,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                'deleted_db_entries' => $totalDeleted,
                'deleted_files' => $deletedFiles
            ]);

            $this->info("Log rotation completed successfully!");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error during log rotation: " . $e->getMessage());
            Log::channel('app')->error("Log rotation failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}

