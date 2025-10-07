<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class AnalyticsAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'analytics_metric_id', 'condition', 'threshold', 'channel',
        'recipients', 'last_triggered_at', 'is_active',
    ];

    protected $casts = [
        'threshold' => 'decimal:4',
        'recipients' => 'array',
        'last_triggered_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function metric(): BelongsTo
    {
        return $this->belongsTo(AnalyticsMetric::class, 'analytics_metric_id');
    }

    public function dataSource(): HasOneThrough
    {
        return $this->hasOneThrough(
            AnalyticsDataSource::class,
            AnalyticsMetric::class,
            'id',
            'id',
            'analytics_metric_id',
            'analytics_data_source_id'
        );
    }

    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\AnalyticsAlertFactory::new();
    }
}
