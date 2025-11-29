<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Setup Job Model
 *
 * Represents a running or completed server setup job.
 * Tracks progress, status, and logs for each setup execution.
 */
class SetupJob extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Setup\Database\Factories\SetupJobFactory::new();
    }

    protected $fillable = [
        'job_id',
        'setup_template_id',
        'target_host',
        'target_hostname',
        'status',
        'configuration',
        'components_status',
        'output_log',
        'error_log',
        'progress_percentage',
        'started_at',
        'completed_at',
        'duration_seconds',
        'initiated_by',
    ];

    protected $casts = [
        'configuration' => 'array',
        'components_status' => 'array',
        'progress_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    protected $attributes = [
        'configuration' => '{}',
        'components_status' => '{}',
        'progress_percentage' => 0,
    ];

    /**
     * Setup template relationship
     */
    public function setupTemplate(): BelongsTo
    {
        return $this->belongsTo(SetupTemplate::class);
    }

    /**
     * Check if job is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if job is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark job as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark job as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
            'duration_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed(?string $error = null): void
    {
        $update = [
            'status' => 'failed',
            'completed_at' => now(),
            'duration_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ];

        if ($error) {
            $update['error_log'] = $this->error_log ? $this->error_log."\n".$error : $error;
        }

        $this->update($update);
    }

    /**
     * Update job progress
     */
    public function updateProgress(int $percentage, ?string $output = null): void
    {
        $update = ['progress_percentage' => min(100, max(0, $percentage))];

        if ($output) {
            $update['output_log'] = $this->output_log ? $this->output_log."\n".$output : $output;
        }

        $this->update($update);
    }

    /**
     * Get running jobs
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Get completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get jobs for specific host
     */
    public function scopeForHost($query, string $host)
    {
        return $query->where('target_host', $host);
    }
}
