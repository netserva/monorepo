<?php

namespace NetServa\Ops\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use NetServa\Ops\Models\BackupJob;
use NetServa\Ops\Models\BackupRepository;
use NetServa\Ops\Models\BackupSnapshot;

class BackupService
{
    /**
     * Create backup repository
     */
    public function createRepository(array $data): BackupRepository
    {
        return BackupRepository::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'storage_driver' => $data['type'] ?? 'local',
            'storage_path' => $data['path'] ?? '/backups',
            'encryption_enabled' => $data['encryption_enabled'] ?? false,
            'retention_days' => $data['retention_days'] ?? 30,
            'is_active' => true,
        ]);
    }

    /**
     * Create backup job
     */
    public function createBackupJob(BackupRepository $repository, array $config): BackupJob
    {
        return BackupJob::create([
            'name' => $config['name'] ?? 'Backup Job - '.now()->format('Y-m-d H:i:s'),
            'job_id' => uniqid('job_'),
            'description' => $config['description'] ?? 'Backup job created by service',
            'backup_repository_id' => $repository->id,
            'target_host' => $config['target_host'] ?? 'localhost',
            'source_paths' => $config['sources'] ?? [],
            'destination_path' => $config['destination_path'] ?? '/backups/default',
            'exclude_patterns' => $config['exclude_patterns'] ?? [],
            'backup_type' => $config['backup_type'] ?? 'files',
            'retention_days' => $config['retention_days'] ?? 30,
            'enabled' => true,
            'status' => 'pending',
        ]);
    }

    /**
     * Execute backup job
     */
    public function executeBackup(BackupJob $job): BackupSnapshot
    {
        $snapshot = BackupSnapshot::create([
            'backup_job_id' => $job->id,
            'backup_repository_id' => $job->backup_repository_id,
            'snapshot_id' => Str::uuid(),
            'storage_path' => $this->generateBackupPath($job),
            'backup_type' => $job->backup_type === 'files' ? 'full' : $job->backup_type,
            'started_at' => now(),
            'status' => 'running',
            'created_by' => auth()->user()?->name ?? 'System',
            'trigger_type' => 'manual',
            'is_encrypted' => $job->backupRepository->shouldEncrypt(),
        ]);

        try {
            $snapshot->start();
            $this->executeTarBackup($job, $snapshot->storage_path);

            $fileSize = file_exists($snapshot->storage_path) ? filesize($snapshot->storage_path) : 0;

            $snapshot->complete([
                'original_size_bytes' => $fileSize,
                'stored_size_bytes' => $fileSize,
            ]);

            return $snapshot;
        } catch (\Exception $e) {
            $snapshot->fail($e->getMessage());
            throw $e;
        }
    }

    /**
     * Restore from snapshot
     */
    public function restoreFromSnapshot(BackupSnapshot $snapshot, string $restorePath): array
    {
        try {
            $command = "tar -xzf {$snapshot->storage_path} -C {$restorePath}";
            $result = Process::run($command);

            if ($result->successful()) {
                return [
                    'success' => true,
                    'restore_path' => $restorePath,
                    'message' => 'Backup restored successfully',
                ];
            }

            return [
                'success' => false,
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify backup integrity
     */
    public function verifyBackupIntegrity(BackupSnapshot $snapshot): array
    {
        try {
            // Simple file existence and size check
            if (! file_exists($snapshot->storage_path)) {
                return ['valid' => false, 'error' => 'Backup file not found'];
            }

            $fileSize = filesize($snapshot->storage_path);
            if ($fileSize === 0) {
                return ['valid' => false, 'error' => 'Backup file is empty'];
            }

            // Generate checksum if not exists
            if (! $snapshot->checksum_value) {
                $snapshot->calculateChecksum();
            }

            return [
                'valid' => true,
                'file_size' => $fileSize,
                'checksum' => $snapshot->checksum_value,
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Apply simple retention policy
     */
    public function applyRetentionPolicy(BackupRepository $repository): array
    {
        $cutoffDate = now()->subDays($repository->retention_days);
        $oldSnapshots = BackupSnapshot::where('backup_repository_id', $repository->id)
            ->where('created_at', '<', $cutoffDate)
            ->where('status', 'completed')
            ->get();

        $deletedCount = 0;
        $deletedSnapshots = [];

        foreach ($oldSnapshots as $snapshot) {
            try {
                // Delete physical file if exists
                if (file_exists($snapshot->storage_path)) {
                    unlink($snapshot->storage_path);
                }

                $deletedSnapshots[] = $snapshot->id;
                $snapshot->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                logger()->error("Failed to delete snapshot {$snapshot->id}: ".$e->getMessage());
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'deleted_snapshots' => $deletedSnapshots,
            'cutoff_date' => $cutoffDate,
            'retention_days' => $repository->retention_days,
        ];
    }

    /**
     * Get backup statistics
     */
    public function getBackupStatistics(BackupRepository $repository): array
    {
        $snapshots = $repository->backupSnapshots();

        return [
            'total_snapshots' => $snapshots->count(),
            'completed_snapshots' => $snapshots->where('status', 'completed')->count(),
            'failed_snapshots' => $snapshots->where('status', 'failed')->count(),
            'total_size_bytes' => $snapshots->where('status', 'completed')->sum('stored_size_bytes'),
            'latest_backup' => $repository->last_backup_at,
            'repository_active' => $repository->is_active,
        ];
    }

    /**
     * Calculate estimated backup size
     */
    public function calculateBackupSize(array $paths): array
    {
        $totalBytes = 0;

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $command = "du -sb {$path}";
                $result = Process::run($command);

                if ($result->successful()) {
                    $output = trim($result->output());
                    $bytes = (int) explode("\t", $output)[0];
                    $totalBytes += $bytes;
                }
            } elseif (file_exists($path)) {
                $totalBytes += filesize($path);
            }
        }

        return [
            'total_bytes' => $totalBytes,
            'human_readable' => $this->formatBytes($totalBytes),
            'paths_count' => count($paths),
        ];
    }

    /**
     * Execute incremental backup
     */
    public function executeIncrementalBackup(BackupJob $job, BackupSnapshot $baseSnapshot): BackupSnapshot
    {
        $snapshot = BackupSnapshot::create([
            'backup_job_id' => $job->id,
            'backup_repository_id' => $job->backup_repository_id,
            'snapshot_id' => Str::uuid(),
            'storage_path' => $this->generateBackupPath($job),
            'backup_type' => 'incremental',
            'parent_snapshot_id' => $baseSnapshot->id,
            'started_at' => now(),
            'status' => 'running',
            'created_by' => auth()->user()?->name ?? 'System',
            'trigger_type' => 'manual',
            'is_encrypted' => $job->backupRepository->shouldEncrypt(),
        ]);

        try {
            $sources = implode(' ', $job->sources);
            $command = "rsync -av --link-dest={$baseSnapshot->storage_path} {$sources} {$snapshot->storage_path}";
            $result = Process::run($command);

            if ($result->successful()) {
                $fileSize = file_exists($snapshot->storage_path) ? filesize($snapshot->storage_path) : 0;
                $snapshot->complete([
                    'original_size_bytes' => $fileSize,
                    'stored_size_bytes' => $fileSize,
                ]);
            } else {
                $snapshot->fail($result->errorOutput());
            }

            return $snapshot;
        } catch (\Exception $e) {
            $snapshot->fail($e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper methods
     */
    protected function generateBackupPath(BackupJob $job): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$job->id}_{$timestamp}.tar.gz";

        return rtrim($job->backupRepository->storage_path, '/').'/'.$filename;
    }

    protected function executeTarBackup(BackupJob $job, string $snapshotPath): void
    {
        $sources = implode(' ', $job->sources);
        $excludes = '';

        if (! empty($job->exclude_patterns)) {
            foreach ($job->exclude_patterns as $pattern) {
                $excludes .= " --exclude='{$pattern}'";
            }
        }

        $command = "tar -czf {$snapshotPath} {$excludes} {$sources}";
        $result = Process::run($command);

        if (! $result->successful()) {
            throw new \Exception('Backup failed: '.$result->errorOutput());
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return number_format($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
