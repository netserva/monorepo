<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsDataSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'connection', 'description', 'is_active',
    ];

    protected $casts = [
        'connection' => 'array',
        'is_active' => 'boolean',
    ];

    public function metrics(): HasMany
    {
        return $this->hasMany(AnalyticsMetric::class);
    }

    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\AnalyticsDataSourceFactory::new();
    }
}
