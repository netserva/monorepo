<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnalyticsVisualization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'type', 'metric_ids', 'config',
        'refresh_interval', 'is_active', 'analytics_dashboard_id',
        'dashboard_position_x', 'dashboard_position_y',
        'dashboard_width', 'dashboard_height',
    ];

    protected $casts = [
        'metric_ids' => 'array',
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    public function metrics(): BelongsToMany
    {
        return $this->belongsToMany(AnalyticsMetric::class, 'metric_ids');
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(AnalyticsDashboard::class, 'analytics_dashboard_id');
    }

    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\AnalyticsVisualizationFactory::new();
    }
}
