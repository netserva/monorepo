<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'analytics_data_source_id', 'query',
        'value', 'collected_at', 'frequency', 'unit', 'type',
        'threshold_warning', 'threshold_critical', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'threshold_warning' => 'decimal:4',
        'threshold_critical' => 'decimal:4',
        'collected_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(AnalyticsDataSource::class, 'analytics_data_source_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AnalyticsAlert::class);
    }

    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\AnalyticsMetricFactory::new();
    }
}
