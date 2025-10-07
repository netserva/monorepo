<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'widgets', 'layout_columns',
        'refresh_interval', 'is_public', 'is_active',
    ];

    protected $casts = [
        'widgets' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\AnalyticsDashboardFactory::new();
    }
}
