<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Simple migration job model for basic server migration operations
 */
class MigrationJob extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\MigrationJobFactory::new();
    }

    protected $fillable = [
        'source_server',
        'target_server',
        'domain',
        'status',
        'progress',
        'started_at',
        'completed_at',
        'error_message',
        'migration_type',
        'configuration',
        'ssh_host_id',
        'job_name',
        'description',
        'dry_run',
        'step_backup',
        'step_cleanup',
    ];

    protected $casts = [
        'dry_run' => 'boolean',
        'step_backup' => 'boolean',
        'step_cleanup' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'integer',
        'configuration' => 'array',
    ];

    public function sshHost(): BelongsTo
    {
        return $this->belongsTo(SshHost::class);
    }
}
