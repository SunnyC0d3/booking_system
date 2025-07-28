<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupOldLogs extends Command
{
    protected $signature = 'logs:cleanup
                            {--days=30 : Delete logs older than this many days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up old log files to free disk space';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        if ($dryRun) {
            $this->info('Running in dry-run mode - no files will be deleted');
        }

        $this->info("Cleaning up log files older than {$days} days (before {$cutoffDate->format('Y-m-d')})");

        $logPath = storage_path('logs');
        $files = glob($logPath . '/*.log');

        $deleted = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            $fileTime = Carbon::createFromTimestamp(filemtime($file));

            if ($fileTime->lt($cutoffDate)) {
                $fileSize = filesize($file);
                $totalSize += $fileSize;

                $this->line("Found old log: " . basename($file) . " (" . $this->formatBytes($fileSize) . ")");

                if (!$dryRun) {
                    if (unlink($file)) {
                        $deleted++;
                        $this->line("✓ Deleted: " . basename($file));
                    } else {
                        $this->error("✗ Failed to delete: " . basename($file));
                    }
                } else {
                    $deleted++;
                }
            }
        }

        $this->line('');
        $this->info('=== Cleanup Summary ===');

        if ($dryRun) {
            $this->line("Files that would be deleted: {$deleted}");
            $this->line("Space that would be freed: " . $this->formatBytes($totalSize));
        } else {
            $this->line("Files deleted: {$deleted}");
            $this->line("Space freed: " . $this->formatBytes($totalSize));
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
