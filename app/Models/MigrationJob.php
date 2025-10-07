<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MigrationJob extends Model
{
    use HasFactory, SoftDeletes;

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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'configuration' => 'array',
        'progress' => 'integer',
    ];

    public function sshHost(): BelongsTo
    {
        return $this->belongsTo(\Ns\Ssh\Models\SshHost::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'running' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'gray'
        };
    }
}
